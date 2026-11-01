<?php

declare(strict_types=1);

namespace Nvl\Forms\Services;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\LocalFilesystemAdapter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Nvl\Forms\Contracts\FormEntryDeletionPolicy;
use Nvl\Forms\Contracts\FormEntryPrivacyPolicy;
use Nvl\Forms\Exceptions\FormException;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormAnalytic;
use Nvl\Forms\Models\FormEntry;
use Nvl\Forms\Models\FormRateLimit;
use Nvl\Support\Ownership\Contracts\OwnershipBoundary;
use PDO;

/**
 * Runs explicit, bounded retention for one admitted Form under current host policy.
 *
 * @phpstan-type RetentionSource array{connection: Connection, pdo: PDO, read: PDO|Closure|null, direct: PDO|Closure|null}
 *
 * @api
 */
final readonly class FormRetention
{
    /** Preserve native entry deletion, privacy and legal-hold rules. */
    public function __construct(private FormEntries $entries, private FormEntryPrivacyPolicy $privacy, private FormEntryDeletionPolicy $deletion, private OwnershipBoundary $ownership, private FormEntryExportService $exports) {}

    /**
     * Prune configured ages only; missing enablement, Gate or all ages fail closed.
     * Dry runs invoke the same policies without deleting rows or files. Completed
     * records may precede a later denial. All runs reject host outer
     * transactions; database records commit individually and file deletion is immediate.
     *
     * @return array{entries: int, analytics: int, rates: int, exports: int, has_more: bool}
     */
    public function prune(Form|string $form, Authenticatable $actor, bool $dryRun = false): array
    {
        $actor = FormManagement::authorize($actor);
        $this->assertEnabled();
        if ($form instanceof Form) {
            $this->ownership->assertRecord($form, 'forms.forms');
        }
        $form = FormModelRegistry::query()->useWritePdo()->findOrFail($form instanceof Form ? $form->id : $form);
        $this->ownership->assertRecord($form, 'forms.forms');
        $connection = $form->getConnection();
        $source = ['connection' => $connection, 'pdo' => $connection->getPdo(), 'read' => $connection->getRawReadPdo(), 'direct' => $connection->getRawDirectPdo()];
        if ($connection->transactionLevel() !== 0 || $source['pdo']->inTransaction()) {
            throw new FormException('Run Form retention and its dry previews outside host transactions.');
        }
        $now = CarbonImmutable::now();
        $ages = [];
        foreach (['entries', 'analytics', 'rates', 'exports'] as $kind) {
            $value = config('nvl-forms.retention.'.$kind.($kind === 'exports' ? '_hours' : '_days'));
            if ($value === null) {
                continue;
            }
            if (! is_int($value) || $value < 1 || $value > 36500) {
                throw new FormException('Form retention ages must be explicit positive integers no greater than 36,500 days or hours.');
            }
            $ages[$kind] = $kind === 'exports' ? $now->subHours($value) : $now->subDays($value);
        }
        if ($ages === []) {
            throw new FormException('Form retention requires at least one explicitly configured age.');
        }
        $remaining = $this->budget('maximum_records', 1000, 10000);
        $batchSize = $this->budget('batch_size', 200, 1000);
        $result = ['entries' => 0, 'analytics' => 0, 'rates' => 0, 'exports' => 0, 'has_more' => false];
        foreach ($ages as $kind => $cutoff) {
            $actor = $this->authorize($form, $actor, $kind, $source);
            [$count, $more] = $kind === 'exports'
                ? $this->exports($form, $actor, $cutoff, $remaining, $dryRun, $source)
                : $this->records($kind, $form, $actor, $cutoff, $now, $remaining, $batchSize, $dryRun, $source);
            $result[$kind] = $count;
            $result['has_more'] = $result['has_more'] || $more;
        }

        return $result;
    }

    /** @param RetentionSource $source
     * @return array{int, bool}
     */
    private function records(string $kind, Form $form, Authenticatable $actor, CarbonImmutable $cutoff, CarbonImmutable $now, int &$remaining, int $batchSize, bool $dryRun, array $source): array
    {
        $model = $this->model($kind);
        if ($model->getConnection() !== $source['connection']) {
            throw new FormException('Form retention requires the same actual parent and child connection.');
        }
        $query = match ($kind) {
            'entries' => FormEntry::query()->useWritePdo()->where('form_id', $form->id)->where('created_at', '<', $cutoff),
            'analytics' => FormAnalytic::query()->useWritePdo()->where('form_id', $form->id)->where('created_at', '<', $cutoff),
            'rates' => FormRateLimit::query()->useWritePdo()->where('form_id', $form->id)->where('window_start', '<', $cutoff)->where(static function ($blocks) use ($now): void {
                $blocks->where('is_blocked', false)->orWhere(static function ($expired) use ($now): void {
                    $expired->where('is_blocked', true)->whereNotNull('blocked_until')->where('blocked_until', '<', $now);
                });
            }),
            default => throw new FormException('Unsupported Form retention resource.'),
        };
        $key = $model->getQualifiedKeyName();
        $after = null;
        $count = 0;
        do {
            $this->assertSource($form, $source);
            $batch = clone $query;
            if ($after !== null) {
                $batch->where($key, '>', $after);
            }
            $take = min($remaining, $batchSize);
            $identifiers = $batch->orderBy($key)->limit($take + 1)->pluck($key)->all();
            $more = count($identifiers) > $take;
            foreach (array_slice($identifiers, 0, $take) as $identifier) {
                if (! is_string($identifier)) {
                    throw new FormException('Form retention requires native scalar record identities.');
                }
                $after = $identifier;
                $remaining--;
                $this->assertSource($form, $source);
                $count += $model->getConnection()->transaction(function () use ($query, $identifier, $form, $actor, $kind, $dryRun, $source, $cutoff): int {
                    try {
                        $actor = $this->authorize($form, $actor, $kind, $source);
                        $record = (clone $query)->whereKey($identifier)->lockForUpdate()->first();
                        if ($record === null) {
                            return 0;
                        }
                        if ($record instanceof FormEntry) {
                            $this->privacy->authorize('retention', $record, $actor);
                            $this->deletion->authorize($record, $actor);
                            Gate::forUser($actor)->authorize('delete', $record);
                            $actor = $this->authorize($form, $actor, $kind, $source);
                            if ($dryRun) {
                                return 1;
                            }
                            if (! (clone $query)->whereKey($identifier)->exists()) {
                                return 0;
                            }

                            return $this->entries->deleteForRetention($record, $cutoff, $actor, function () use ($form, $actor, $kind, $source): void {
                                $this->authorize($form, $actor, $kind, $source);
                            }) ? 1 : 0;
                        }

                        $this->authorize($form, $actor, $kind, $source);
                        if ($dryRun) {
                            return 1;
                        }
                        $deleted = (clone $query)->whereKey($identifier)->delete();
                        if (! is_int($deleted)) {
                            throw new FormException('Form retention requires an exact native deletion count.');
                        }

                        return $deleted;
                    } finally {
                        $this->assertSource($form, $source);
                    }
                }, 3);
                $this->assertSource($form, $source);
            }
        } while ($more && $remaining > 0);

        return [$count, $more];
    }

    /** @param RetentionSource $source
     * @return array{int, bool}
     */
    private function exports(Form $form, Authenticatable $actor, CarbonImmutable $cutoff, int &$remaining, bool $dryRun, array $source): array
    {
        $name = config('nvl-forms.exports.disk', 'local');
        if (! is_string($name) || trim($name) === '' || config('filesystems.disks.'.$name.'.driver', 'local') !== 'local') {
            throw new FormException('Form export retention requires an explicitly selected local disk.');
        }
        $disk = Storage::disk($name);
        if (! $disk instanceof LocalFilesystemAdapter) {
            throw new FormException('Form export retention requires a local filesystem disk.');
        }
        $directory = $this->exports->directory($form, $this->ownership);
        $this->assertSource($form, $source);
        $count = 0;
        foreach ($disk->getDriver()->listContents($directory, false) as $file) {
            if (! $file->isFile() || preg_match('/\A(?:[0-9A-HJKMNP-TV-Z]{26}_[A-Za-z0-9_-]+\.csv|\.[0-9A-HJKMNP-TV-Z]{26}\.partial)\z/D', basename($file->path())) !== 1) {
                continue;
            }
            if ($disk->lastModified($file->path()) >= $cutoff->getTimestamp()) {
                continue;
            }
            if ($remaining === 0) {
                return [$count, true];
            }
            $remaining--;
            $this->authorize($form, $actor, 'exports', $source);
            if (! $disk->exists($file->path()) || $disk->lastModified($file->path()) >= $cutoff->getTimestamp()) {
                continue;
            }
            if (! $dryRun && ! $disk->delete($file->path())) {
                throw new FormException('Unable to delete an expired private form export.');
            }
            $count++;
        }

        return [$count, false];
    }

    /** @param RetentionSource $source */
    private function authorize(Form $form, Authenticatable $actor, string $kind, array $source): Authenticatable
    {
        $this->assertSource($form, $source);
        try {
            $this->assertEnabled();
            $actor = FormManagement::authorize($actor);
            $this->assertSource($form, $source);
            $current = FormModelRegistry::query()->useWritePdo()->findOrFail($form->id);
            if ($current->getConnection() !== $source['connection']) {
                throw new FormException('The selected Form retention source changed.');
            }
            $this->ownership->assertRecord($current, 'forms.forms');
            $ability = config('nvl-forms.retention.authorization_ability');
            if (! is_string($ability) || trim($ability) === '') {
                throw new AuthorizationException('Form retention requires an explicitly configured host Gate.');
            }
            Gate::forUser($actor)->authorize($ability, [$current, $kind]);
            $this->assertSource($current, $source);

            return $actor;
        } finally {
            $this->assertSource($form, $source);
        }
    }

    /** @param RetentionSource $source */
    private function assertSource(Form $form, array $source): void
    {
        if ($source['connection']->getRawPdo() !== $source['pdo']) {
            try {
                if ($source['pdo']->inTransaction()) {
                    $source['pdo']->rollBack();
                }
            } finally {
                $source['connection']->setPdo($source['pdo']);
                try {
                    $source['connection']->disconnect();
                } finally {
                    $source['connection']->setPdo($source['pdo'])->setReadPdo($source['read'])->setDirectPdo($source['direct']);
                }
            }
            throw new FormException('The selected Form retention source changed; retention stopped.');
        }
        if ($form->getConnection() !== $source['connection']) {
            throw new FormException('The selected Form retention source changed.');
        }
    }

    private function assertEnabled(): void
    {
        if (config('nvl-forms.retention.enabled') !== true) {
            throw new AuthorizationException('Form retention is disabled until explicitly configured.');
        }
    }

    private function budget(string $key, int $default, int $maximum): int
    {
        $value = config('nvl-forms.retention.'.$key, $default);
        if (! is_int($value) || $value < 1 || $value > $maximum) {
            throw new FormException('Form retention budgets must be positive bounded integers.');
        }

        return $value;
    }

    private function model(string $kind): Model
    {
        return match ($kind) {
            'entries' => new FormEntry,
            'analytics' => new FormAnalytic,
            'rates' => new FormRateLimit,
            default => throw new FormException('Unsupported Form retention resource.'),
        };
    }
}
