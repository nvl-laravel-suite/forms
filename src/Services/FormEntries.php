<?php

declare(strict_types=1);

namespace Nvl\Forms\Services;

use Carbon\CarbonImmutable;
use Closure;
use Exception;
use Generator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Filesystem\LocalFilesystemAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Nvl\Forms\Contracts\FormEntryDeletionPolicy;
use Nvl\Forms\Contracts\FormEntryPrivacyPolicy;
use Nvl\Forms\Events\FormChanged;
use Nvl\Forms\Events\FormEntryChanged;
use Nvl\Forms\Exceptions\FormException;
use Nvl\Forms\Exceptions\FormOwnershipException;
use Nvl\Forms\Exceptions\FormRetentionPredicateChanged;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormEntry;
use Nvl\Forms\Ownership\FormsOwnership;
use Nvl\Forms\Support\FormIdentifier;
use Nvl\Support\Events\DomainEventDispatcher;
use Nvl\Support\Ownership\Contracts\OwnershipBoundary;
use PDO;
use Throwable;

/**
 * Native Forms workflow family.
 *
 * @api
 */
final class FormEntries
{
    public function __construct(private readonly FormEntryDeletionPolicy $deletionPolicy, private DomainEventDispatcher $domainEvents, private FormEntryPrivacyPolicy $privacyPolicy, private readonly FormEntryExportService $exportService, private readonly OwnershipBoundary $ownership) {}

    /**
     * Execute the form entry deletion.
     *
     * @param  FormEntry|string  $formEntry  Form entry instance or identifier
     * @param  Authenticatable|null  $actor  Actor performing the deletion
     * @return bool True if deletion was successful
     *
     * @throws Exception|Throwable If deletion fails or entry cannot be deleted
     */
    public function delete(FormEntry|string $formEntry, ?Authenticatable $actor = null): bool
    {
        return $this->deleteEntry($formEntry, $actor);
    }

    /**
     * @internal Reuse native deletion policy, counters and events with a fixed retention predicate.
     *
     * @param  Closure(): void  $authorize
     */
    public function deleteForRetention(FormEntry|string $formEntry, CarbonImmutable $cutoff, Authenticatable $actor, Closure $authorize): bool
    {
        return $this->deleteEntry($formEntry, $actor, $cutoff, $authorize);
    }

    /** @param (Closure(): void)|null $authorize */
    private function deleteEntry(FormEntry|string $formEntry, ?Authenticatable $actor, ?CarbonImmutable $cutoff = null, ?Closure $authorize = null): bool
    {
        $actor = FormManagement::authorize($actor);
        $entryId = FormIdentifier::of($formEntry, FormEntry::class);
        $connection = (new FormEntry)->getConnection();
        $operation = function () use ($entryId, $actor, $cutoff, $authorize, $connection): array {
            if ($authorize !== null) {
                $authorize();
            }
            $formEntry = FormEntry::query()->lockForUpdate()->findOrFail($entryId);
            $form = FormModelRegistry::query()->lockForUpdate()->findOrFail($formEntry->form_id);
            $formEntry->setRelation('form', $form);
            $this->deleteFormEntryValidateCanDelete($formEntry, $actor);
            if ($authorize !== null) {
                $authorize();
            }
            $wasSpam = $formEntry->is_spam;
            if ($cutoff === null) {
                $deleted = $formEntry->delete();
            } else {
                if ($authorize === null) {
                    throw new FormException('Conditional Form retention requires current authorization.');
                }
                $table = $formEntry->getTable();
                $identifier = $formEntry->identifier();
                $assertEntrySource = static function () use ($formEntry, $connection, $table, $identifier): void {
                    if ($formEntry->getConnection() !== $connection || $formEntry->getTable() !== $table
                        || $formEntry->identifier() !== $identifier || $formEntry->getRawOriginal($formEntry->getKeyName()) !== $identifier) {
                        throw new FormException('The selected Form retention entry source changed.');
                    }
                };
                try {
                    $deleted = $formEntry->deleteBefore($cutoff, static function () use ($authorize, $assertEntrySource): void {
                        $assertEntrySource();
                        $authorize();
                        $assertEntrySource();
                    });
                } catch (FormRetentionPredicateChanged) {
                    return ['deleted' => false, 'form' => $form, 'entry' => $formEntry, 'was_spam' => $wasSpam];
                }
            }
            if ($authorize !== null) {
                $authorize();
            }
            if ($cutoff !== null && $deleted === null) {
                return ['deleted' => false, 'form' => $form, 'entry' => $formEntry, 'was_spam' => $wasSpam];
            }
            if ($deleted !== true) {
                throw new Exception((string) trans('nvl-forms::forms/shared.messages.error.delete_failed', ['item' => (string) trans('nvl-forms::entries/general.entities.singular')]));
            }
            if ($wasSpam && $form->spam_count > 0) {
                $form->decrement('spam_count');
            } elseif (! $wasSpam && $form->submissions_count > 0) {
                $form->decrement('submissions_count');
            }
            $freshForm = $form->fresh();
            if (! $freshForm instanceof Form) {
                $freshForm = $form;
            }
            $this->domainEvents->dispatch(FormEntryChanged::for(form: $form, entry: $formEntry, operation: 'deleted', actor: $actor, context: ['was_spam' => $wasSpam]), $form->getConnection());

            return ['deleted' => $deleted, 'form' => $freshForm, 'entry' => $formEntry, 'was_spam' => $wasSpam];
        };
        if ($cutoff !== null && $connection->transactionLevel() === 0) {
            throw new FormException('Conditional Form retention requires its owned record transaction.');
        }
        /** @var array{deleted: bool, form: Form, entry: FormEntry, was_spam: bool} $result */
        $result = $cutoff === null ? $connection->transaction($operation) : $operation();

        return $result['deleted'];
    }

    /**
     * Validate that the entry can be deleted.
     *
     * @param  FormEntry  $formEntry  The form entry to validate
     *
     * @throws Exception If entry cannot be deleted
     */
    private function deleteFormEntryValidateCanDelete(FormEntry $formEntry, ?Authenticatable $actor = null): void
    {
        // Check if user has permission to delete this entry
        if ($actor !== null && method_exists($actor, 'can') && ! $actor->can('delete', $formEntry)) {
            throw new Exception((string) trans('nvl-forms::forms/shared.messages.error.permission_denied'));
        }
        $this->deletionPolicy->authorize($formEntry, $actor);
    }

    private const array REDACTABLE = ['subject', 'email', 'first_name', 'last_name', 'phone', 'address', 'body', 'submission_data', 'ip_address', 'user_agent', 'session_id'];

    /**
     * @param  list<string>  $fields
     */
    public function redact(FormEntry|string $entry, array $fields, ?Authenticatable $actor = null): FormEntry
    {
        $actor = FormManagement::authorize($actor);
        $unknown = array_values(array_diff($fields, self::REDACTABLE));
        if ($fields === [] || $unknown !== []) {
            throw new InvalidArgumentException('Redaction fields must use the documented allowlist.');
        }
        $updated = (new FormEntry)->getConnection()->transaction(function () use ($entry, $fields, $actor): FormEntry {
            $entryId = FormIdentifier::of($entry, FormEntry::class);
            $model = FormEntry::query()->with('form')->lockForUpdate()->findOrFail($entryId);
            $this->privacyPolicy->authorize('redact', $model, $actor);
            $attributes = array_fill_keys($fields, null);
            $attributes['redacted_at'] = now();
            $model->forceFill($attributes)->save();
            $this->domainEvents->dispatch(FormEntryChanged::for($model->form, $model, 'redacted', $actor, ['field_count' => count($fields)]), $model->getConnection());

            return $model->refresh()->load('form');
        });

        return $updated;
    }

    public function anonymize(FormEntry|string $entry, ?Authenticatable $actor = null): FormEntry
    {
        $actor = FormManagement::authorize($actor);
        $updated = (new FormEntry)->getConnection()->transaction(function () use ($entry, $actor): FormEntry {
            $entryId = FormIdentifier::of($entry, FormEntry::class);
            $model = FormEntry::query()->with('form')->lockForUpdate()->findOrFail($entryId);
            $this->privacyPolicy->authorize('anonymize', $model, $actor);
            $model->forceFill(['subject' => null, 'email' => null, 'first_name' => null, 'last_name' => null, 'phone' => null, 'address' => null, 'body' => null, 'submission_data' => null, 'ip_address' => null, 'user_agent' => null, 'session_id' => null, 'security_flags' => null, 'redacted_at' => now(), 'anonymized_at' => now()])->save();
            $this->domainEvents->dispatch(FormEntryChanged::for($model->form, $model, 'anonymized', $actor), $model->getConnection());

            return $model->refresh()->load('form');
        });

        return $updated;
    }

    /**
     * Persist a security flag key/value pair on the given entry.
     *
     * @param  FormEntry|string  $entry  Entry model or identifier
     * @param  string  $key  Security flag key
     * @param  mixed  $value  Security flag value
     * @return FormEntry Updated entry model
     *
     * @throws Throwable
     */
    public function flag(FormEntry|string $entry, string $key, mixed $value, ?Authenticatable $actor = null): FormEntry
    {
        $actor = FormManagement::authorize($actor);
        /** @var array{entry: FormEntry, form: Form} $result */
        $result = (new FormEntry)->getConnection()->transaction(function () use ($entry, $key, $value, $actor): array {
            $entryId = FormIdentifier::of($entry, FormEntry::class);
            $entryModel = FormEntry::query()->lockForUpdate()->findOrFail($entryId);
            $entryModel->setSecurityFlag($key, $value);
            $entryModel->save();
            $freshEntry = $entryModel->refresh()->load('form');
            $form = $freshEntry->form;
            $this->domainEvents->dispatch(FormEntryChanged::for(form: $form, entry: $freshEntry, operation: 'security_flag_added', actor: $actor, context: ['flag_key' => $key]), $form->getConnection());

            return ['entry' => $freshEntry, 'form' => $form->fresh() ?? $form];
        });

        return $result['entry'];
    }

    /**
     * Mark the given entry as spam.
     *
     * @param  FormEntry|string  $entry  Entry model or identifier
     * @param  string|null  $reason  Optional reason for spam classification
     * @return FormEntry Updated entry model
     *
     * @throws Throwable
     */
    public function markSpam(FormEntry|string $entry, ?string $reason = null, ?Authenticatable $actor = null): FormEntry
    {
        $actor = FormManagement::authorize($actor);
        /** @var array{entry: FormEntry, form: Form} $result */
        $result = (new FormEntry)->getConnection()->transaction(function () use ($entry, $reason, $actor): array {
            $entryId = FormIdentifier::of($entry, FormEntry::class);
            $entryModel = FormEntry::query()->lockForUpdate()->findOrFail($entryId);
            $form = FormModelRegistry::query()->lockForUpdate()->findOrFail($entryModel->form_id);
            $wasSpam = $entryModel->is_spam === true;
            $entryModel->setSecurityFlag('marked_spam_at', now()->toISOString());
            if ($reason !== null && $reason !== '') {
                $entryModel->setSecurityFlag('spam_reason', $reason);
            }
            $entryModel->update(['is_spam' => true]);
            if (! $wasSpam) {
                $form->increment('spam_count');
                if ($form->submissions_count > 0) {
                    $form->decrement('submissions_count');
                }
            }
            $freshEntry = $entryModel->refresh();
            $freshEntry->setRelation('form', $form);
            $this->domainEvents->dispatch(FormEntryChanged::for(form: $form, entry: $freshEntry, operation: 'marked_as_spam', actor: $actor, context: ['has_reason' => is_string($reason) && $reason !== '']), $form->getConnection());

            return ['entry' => $freshEntry, 'form' => $form->fresh() ?? $form];
        });

        return $result['entry'];
    }

    /**
     * Mark the given entry as legitimate.
     *
     * @param  FormEntry|string  $entry  Entry model or identifier
     * @return FormEntry Updated entry model
     *
     * @throws Throwable
     */
    public function markLegitimate(FormEntry|string $entry, ?Authenticatable $actor = null): FormEntry
    {
        $actor = FormManagement::authorize($actor);
        /** @var array{entry: FormEntry, form: Form, was_spam: bool} $result */
        $result = (new FormEntry)->getConnection()->transaction(function () use ($entry, $actor): array {
            $entryId = FormIdentifier::of($entry, FormEntry::class);
            $entryModel = FormEntry::query()->lockForUpdate()->findOrFail($entryId);
            $form = FormModelRegistry::query()->lockForUpdate()->findOrFail($entryModel->form_id);
            $wasSpam = $entryModel->is_spam === true;
            $entryModel->setSecurityFlag('marked_legitimate_at', now()->toISOString());
            $entryModel->update(['is_spam' => false]);
            if ($wasSpam) {
                if ($form->spam_count > 0) {
                    $form->decrement('spam_count');
                }
                $form->increment('submissions_count');
            }
            $freshEntry = $entryModel->refresh()->load('form');
            $form = $freshEntry->form;
            $this->domainEvents->dispatch(FormEntryChanged::for(form: $form, entry: $freshEntry, operation: 'marked_as_legitimate', actor: $actor, context: ['was_spam' => $wasSpam]), $form->getConnection());

            return ['entry' => $freshEntry, 'form' => $form->fresh() ?? $form, 'was_spam' => $wasSpam];
        });

        return $result['entry'];
    }

    /**
     * Execute the form entries export with permission checking and progress tracking.
     *
     * Snapshots matching cells within configured budgets, then streams that exact
     * snapshot to a private local artifact after checking current authority again.
     *
     * @param  Form|string  $form  Form instance or identifier
     * @param  array<string, mixed>  $options  Export options (date_from, date_to, limit, has_contact_info, include_submission_data, include_sensitive_data)
     * @param  Authenticatable|null  $actor  Authenticated actor requesting the export
     * @return string Absolute path to the generated CSV file
     *
     * @throws Exception When permission denied, no data to export, or export fails
     */
    public function export(Form|string $form, array $options = [], ?Authenticatable $actor = null): string
    {
        $actor = FormManagement::authorize($actor);
        if ($form instanceof Form) {
            $this->ownership->assertRecord($form, 'forms.forms');
        }
        $form = FormModelRegistry::query()->useWritePdo()->findOrFail(FormIdentifier::of($form, Form::class));
        $this->ownership->assertRecord($form, 'forms.forms');
        $source = $form->getConnection();
        $pdo = $source->getPdo();
        $this->exportFormEntriesValidateExportPermissions($form, $actor);
        $this->assertExportSource($form, $source, $pdo);
        $configuration = $this->exportConfiguration();
        $options = $this->exportOptions($options, $configuration['maximum_rows']);
        $driver = config('filesystems.disks.'.$configuration['disk'].'.driver');
        if ($driver !== null && $driver !== 'local') {
            throw new FormException('Form entry exports require a local filesystem disk that returns an absolute path.');
        }
        $disk = Storage::disk($configuration['disk']);
        if (! $disk instanceof LocalFilesystemAdapter) {
            throw new FormException('Form entry exports require a local filesystem disk that returns an absolute path.');
        }
        $exportDirectory = $this->exportService->directory($form, $this->ownership);
        $this->assertExportSource($form, $source, $pdo);
        $rawActorId = $actor->getAuthIdentifier();
        $actorId = is_string($rawActorId) || is_int($rawActorId) ? $rawActorId : null;
        $exportId = Str::ulid()->toString();
        $progressKey = $this->ownership->key('forms.forms', 'nvl:forms:export-progress:'.($actorId !== null ? (string) $actorId : 'guest').':'.$form->id.':'.$exportId);
        Cache::put($progressKey, ['status' => 'started', 'progress' => 0, 'total' => null, 'started_at' => now()->toISOString()], now()->addHours());
        $snapshot = null;
        $csv = null;
        $partialPath = $exportDirectory.'/.'.$exportId.'.partial';
        $path = null;
        try {
            $snapshot = $this->exportService->snapshot($this->exportEntries($form, $options, $configuration['chunk_size'], $configuration['maximum_rows']), $options, $configuration['maximum_rows'], $configuration['maximum_dynamic_fields'], $configuration['maximum_bytes']);
            if ($snapshot['rows'] === 0) {
                throw new FormException((string) trans('nvl-forms::forms/shared.messages.error.no_export_data', ['items' => (string) trans('nvl-forms::entries/general.entities.plural')]));
            }
            $csv = tmpfile();
            if ($csv === false) {
                throw new FormException('Unable to open the private form entry CSV stream.');
            }
            $bytes = $this->exportService->writeSnapshot($snapshot, $csv, $options, $configuration['maximum_bytes']);
            $filename = $this->exportService->generateFilename($form, $actorId);
            $path = $exportDirectory.'/'.$exportId.'_'.$filename;
            if (! $disk->put($partialPath, $csv, ['visibility' => 'private'])) {
                throw new FormException('Unable to store the form entry export.');
            }
            $actor = FormManagement::authorize($actor);
            $this->assertExportSource($form, $source, $pdo);
            $form = FormModelRegistry::query()->useWritePdo()->findOrFail($form->id);
            $this->assertExportSource($form, $source, $pdo);
            $this->ownership->assertRecord($form, 'forms.forms');
            $this->exportFormEntriesValidateExportPermissions($form, $actor);
            $this->assertExportSource($form, $source, $pdo);
            if (! $disk->move($partialPath, $path)) {
                throw new FormException('Unable to publish the complete form entry export.');
            }
            Cache::put($progressKey, ['status' => 'completed', 'progress' => 100], now()->addHours());
            $this->domainEvents->dispatch(FormChanged::for(form: $form, operation: 'entries_exported', actor: $actor, context: ['entry_count' => $snapshot['rows'], 'file_size' => $bytes]), $form->getConnection());

            return $disk->path($path);
        } catch (Throwable $exception) {
            $disk->delete(array_filter([$partialPath, $path], 'is_string'));
            Cache::put($progressKey, ['status' => 'failed', 'error' => 'The form entry export failed.'], now()->addHours());
            throw $exception;
        } finally {
            if (is_array($snapshot)) {
                fclose($snapshot['stream']);
            }
            if (is_resource($csv)) {
                fclose($csv);
            }
        }
    }

    private function assertExportSource(Form $form, Connection $source, PDO $pdo): void
    {
        if ($form->getConnection() !== $source || $source->getRawPdo() !== $pdo) {
            throw new FormOwnershipException('The form entry export source changed before publication.');
        }
    }

    /**
     * Query entries to export based on filter options.
     *
     * Supports date range filtering, limit, and contact info presence filtering.
     *
     * @param  Form  $form  The form to export entries from
     * @param  array<string, mixed>  $options  Export filter options
     * @return Generator<int, FormEntry>
     */
    private function exportEntries(Form $form, array $options, int $chunkSize, int $maximumRows): Generator
    {
        $query = $form->entries()->getQuery()->useWritePdo();
        if (isset($options['date_from']) && is_string($options['date_from']) && $options['date_from'] !== '') {
            $query->where('created_at', '>=', $options['date_from'].' 00:00:00');
        }
        if (isset($options['date_to']) && is_string($options['date_to']) && $options['date_to'] !== '') {
            $query->where('created_at', '<=', $options['date_to'].' 23:59:59.999999');
        }
        $limit = $maximumRows + 1;
        if (isset($options['limit'])) {
            $requested = filter_var($options['limit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $maximumRows]]);
            if (! is_int($requested)) {
                throw new FormException('The form entry export limit must be a positive bounded integer.');
            }
            $limit = $requested;
        }
        $hasContactInformation = filter_var($options['has_contact_info'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($hasContactInformation) {
            $query->whereRaw('(email IS NOT NULL OR phone IS NOT NULL)');
        }
        $count = 0;
        foreach ([false, true] as $nullTimestamps) {
            $phase = clone $query;
            $nullTimestamps ? $phase->whereNull('created_at') : $phase->whereNotNull('created_at');
            $afterTime = null;
            $afterId = null;
            do {
                $batch = clone $phase;
                if ($afterId !== null) {
                    if ($nullTimestamps) {
                        $batch->where('id', '<', $afterId);
                    } else {
                        $batch->where(static function ($cursor) use ($afterTime, $afterId): void {
                            $cursor->where('created_at', '<', $afterTime)->orWhere(static function ($sameTime) use ($afterTime, $afterId): void {
                                $sameTime->where('created_at', $afterTime)->where('id', '<', $afterId);
                            });
                        });
                    }
                }
                $take = min($chunkSize, $limit - $count);
                if (! $nullTimestamps) {
                    $batch->orderByDesc('created_at');
                }
                $records = $batch->orderByDesc('id')->limit($take)->get();
                foreach ($records as $entry) {
                    yield $entry;
                    $count++;
                }
                if ($count === $limit) {
                    return;
                }
                $last = $records->last();
                if (! $last instanceof FormEntry || $records->count() < $take) {
                    break;
                }
                $afterId = $last->id;
                if (! $nullTimestamps) {
                    $afterTime = $last->getRawOriginal('created_at');
                    if (! is_string($afterTime)) {
                        throw new FormException('Form entry timestamp cursors require persisted scalar values.');
                    }
                }
            } while (true);
        }
    }

    /** @return array{disk: string, maximum_rows: int, maximum_dynamic_fields: int, maximum_bytes: int, chunk_size: int} */
    private function exportConfiguration(): array
    {
        $disk = config('nvl-forms.exports.disk', 'local');
        if (! is_string($disk) || trim($disk) === '') {
            throw new FormException('The form entry export disk must be a nonempty local disk name.');
        }

        return ['disk' => $disk, 'maximum_rows' => $this->exportBudget('maximum_rows', 10000, 1000000), 'maximum_dynamic_fields' => $this->exportBudget('maximum_dynamic_fields', 250, 10000), 'maximum_bytes' => $this->exportBudget('maximum_bytes', 67108864, 1073741824), 'chunk_size' => $this->exportBudget('chunk_size', 200, 1000)];
    }

    private function exportBudget(string $key, int $default, int $maximum): int
    {
        $value = config('nvl-forms.exports.'.$key, $default);
        if (! is_int($value) || $value < 1 || $value > $maximum) {
            throw new FormException('Form entry export budgets must be positive bounded integers.');
        }

        return $value;
    }

    /** @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function exportOptions(array $options, int $maximumRows): array
    {
        if (array_diff(array_keys($options), ['date_from', 'date_to', 'limit', 'has_contact_info', 'include_submission_data', 'include_sensitive_data']) !== []) {
            throw new FormException('The form entry export contains unsupported options.');
        }
        Validator::make($options, ['date_from' => ['sometimes', 'nullable', 'date_format:Y-m-d'], 'date_to' => ['sometimes', 'nullable', 'date_format:Y-m-d'], 'limit' => ['sometimes', 'integer', 'min:1', 'max:'.$maximumRows], 'has_contact_info' => ['sometimes', 'boolean'], 'include_submission_data' => ['sometimes', 'boolean'], 'include_sensitive_data' => ['sometimes', 'boolean']])->validate();

        return $options;
    }

    /**
     * Validate that the actor has permission to export entries.
     *
     * @param  Form  $form  The form being exported
     * @param  Authenticatable|null  $actor  The requesting actor
     *
     * @throws Exception When actor is null or lacks export permission
     */
    private function exportFormEntriesValidateExportPermissions(Form $form, ?Authenticatable $actor = null): void
    {
        if ($actor === null) {
            throw new AuthorizationException((string) trans('nvl-forms::forms/shared.messages.error.authentication_required'));
        }
        if (Gate::forUser($actor)->denies('export', $form)) {
            throw new AuthorizationException((string) trans('nvl-forms::forms/shared.messages.error.permission_denied'));
        }
    }

    /**
     * Execute the filename generation.
     *
     * @param  Form  $form  Form model for export
     * @return string Generated filename
     */
    public function exportFilename(Form $form): string
    {
        return $this->exportService->generateFilename($form, null);
    }

    /**
     * Execute the form entry retrieval.
     *
     * @param  FormEntry|string  $formEntry  Form entry instance or identifier
     * @return FormEntry Form entry with loaded relationships
     */
    public function get(FormEntry|string $formEntry, ?Authenticatable $actor = null): FormEntry
    {
        $actor = FormManagement::authorize($actor);
        // Resolve model if ID provided
        $formEntry = FormEntry::query()->findOrFail(FormIdentifier::of($formEntry, FormEntry::class));

        // Load all relationships needed for display
        return $formEntry->load(['form:'.FormsOwnership::formRelationSelect()]);
    }

    /**
     * Execute the form entries listing.
     *
     * @param  Form|string|null  $form  Form instance, identifier, or null for all entries
     * @param  bool  $paginate  Whether to paginate results
     * @param  int|null  $perPage  Items per page when paginating
     * @param  array<string,mixed>  $filters
     * @return LengthAwarePaginator<int, FormEntry>|Collection<int, FormEntry>
     *
     * @throws FormException When per_page parameter is invalid
     */
    public function paginate(Form|string|null $form = null, bool $paginate = true, ?int $perPage = null, array $filters = [], ?Authenticatable $actor = null): LengthAwarePaginator|Collection
    {
        $actor = FormManagement::authorize($actor);
        $query = FormEntry::query()->select($this->listFormEntriesGetSelectColumns())->with($this->listFormEntriesGetEagerLoadRelations());
        // Filter by specific form if provided
        if ($form !== null) {
            $formId = $form instanceof Form ? $form->id : $form;
            if (Str::isUuid($formId)) {
                $query->where('form_id', $formId);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        foreach (array_intersect_key($filters, array_flip(['subject', 'email', 'first_name', 'last_name', 'phone', 'submitted_from', 'ip_address', 'is_spam'])) as $column => $value) {
            if ($column === 'ip_address' && filter_var($value, FILTER_VALIDATE_IP) === false) {
                $query->whereRaw('1 = 0');

                continue;
            }
            $query->where($column, $value);
        }
        $query->orderByDesc('created_at')->orderBy('id');
        $perPage = $perPage !== null && $perPage > 0 ? $perPage : 20;
        if ($paginate) {
            return $query->paginate($perPage);
        }

        return $query->get();
    }

    /**
     * Get columns to select.
     *
     * @return array<string>
     */
    private function listFormEntriesGetSelectColumns(): array
    {
        return ['id', 'form_id', 'subject', 'email', 'first_name', 'last_name', 'phone', 'submitted_from', 'is_spam', 'redacted_at', 'anonymized_at', 'created_at', ...FormsOwnership::columns('forms.entries')];
    }

    /**
     * Get relationships to eager load.
     *
     * @return array<string>
     */
    private function listFormEntriesGetEagerLoadRelations(): array
    {
        return ['form:'.FormsOwnership::formRelationSelect()];
    }

    /**
     * Execute the ownership validation.
     *
     * @param  Form  $form  Form model
     * @param  FormEntry  $entry  Form entry model
     *
     * @throws FormOwnershipException If entry doesn't belong to form
     */
    public function validateOwnership(Form $form, FormEntry $entry): void
    {
        if ($entry->form_id !== $form->id) {
            throw new FormOwnershipException((string) trans('nvl-forms::forms/shared.messages.error.ownership_mismatch', ['item' => (string) trans('nvl-forms::entries/general.entities.singular'), 'parent' => (string) trans('nvl-forms::forms/general.entities.singular')]));
        }
    }

    /** @param array<string,mixed> $attributes */
    public function update(FormEntry|string $entry, array $attributes, ?Authenticatable $actor = null): FormEntry
    {
        $actor = FormManagement::authorize($actor);
        $allowed = ['subject', 'email', 'firstName', 'lastName', 'phone', 'address', 'body', 'submissionData'];
        if (array_diff(array_keys($attributes), $allowed) !== []) {
            throw new InvalidArgumentException('Entry updates accept only documented content fields.');
        }
        $validated = Validator::make($attributes, FormSubmissions::fieldRules())->validate();

        return (new FormEntry)->getConnection()->transaction(function () use ($entry, $validated, $actor): FormEntry {
            $model = FormEntry::query()->with('form')->lockForUpdate()->findOrFail(FormIdentifier::of($entry, FormEntry::class));
            foreach ($validated as $key => $value) {
                $model->setAttribute(Str::snake($key), $value);
            }
            $model->save();
            $this->domainEvents->dispatch(FormEntryChanged::for($model->form, $model, 'updated', $actor), $model->getConnection());

            return $model->refresh()->load('form');
        });
    }
}
