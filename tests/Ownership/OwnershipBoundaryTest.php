<?php

declare(strict_types=1);
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Nvl\Forms\Models\FormEntry;
use Nvl\Forms\Services\FormEntries;
use Nvl\Forms\Services\FormReader;
use Nvl\Forms\Services\FormRetention;
use Nvl\Forms\Tests\Fixtures\Actor;
use Nvl\Forms\Tests\Fixtures\OwnershipForm;
use Nvl\Support\Ownership\Contracts\OwnershipBoundary;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;

it('keeps equal handles and child records owner local and denies foreign preloaded form handles', function (): void {
    $forms = [];
    foreach ([7, 8] as $id) {
        $forms[$id] = $this->owned($id, fn () => $this->createOwnedForm());
    }
    $this->owned(7, function () use ($forms): void {
        expect(OwnershipForm::count())->toBe(1)->and(app(FormReader::class)->get('contact')->id)->toBe($forms[7]->id);
        expect(fn () => app(FormReader::class)->get($forms[8]))->toThrow(ModelNotFoundException::class)->and(fn () => $forms[8]->saveQuietly())->toThrow(OwnershipViolation::class)->and(fn () => FormEntry::create(['form_id' => $forms[8]->id, 'email' => 'foreign@example.test']))->toThrow(OwnershipViolation::class);
    });
});
it('enforces inherited native scope for raw submission writes', function (): void {
    $a = $this->owned(7, fn () => $this->createOwnedForm());
    $b = $this->owned(8, fn () => $this->createOwnedForm());
    $entry = new FormEntry;
    expect(fn () => $entry->getConnection()->table($entry->getTable())->insert(['id' => (string) Str::uuid(), 'form_id' => $b->id, 'owner_scope_key' => $a->getRawOriginal('owner_scope_key'), 'submitted_from' => 'https://example.test']))->toThrow(QueryException::class);
});
it('partitions export artifacts and progress without implicit retention or foreign Form effects', function (): void {
    Storage::fake('local');
    config(['nvl-forms.authorization.gate' => 'authoritative-group']);
    Gate::define('authoritative-group', static fn (): bool => true);
    $forms = [];
    $paths = [];
    foreach ([8, 7] as $id) {
        $forms[$id] = $this->owned($id, fn () => $this->createOwnedForm());
        $paths[$id] = $this->owned($id, function () use ($forms, $id): string {
            FormEntry::factory()->for($forms[$id], 'form')->create(['email' => 'export-'.$id.'@example.test']);

            return app(FormEntries::class)->export($forms[$id], [], Actor::firstOrFail());
        });
        touch($paths[$id], now()->subHours(2)->timestamp);
    }
    expect(file_exists($paths[8]))->toBeTrue()->and(dirname($paths[7]))->not->toBe(dirname($paths[8]));
    $this->owned(7, function () use ($forms, $paths): void {
        expect(fn () => app(FormEntries::class)->export($forms[8], [], Actor::firstOrFail()))->toThrow(OwnershipViolation::class);
        expect(file_exists($paths[8]))->toBeTrue()->and(file_exists($paths[7]))->toBeTrue();
        $path = app(FormEntries::class)->export($forms[7], [], Actor::firstOrFail());
        expect(file_exists($paths[7]))->toBeTrue()->and(file_exists($paths[8]))->toBeTrue();
        $exportId = explode('_', basename($path), 2)[0];
        $identity = 'nvl:forms:export-progress:1:'.$forms[7]->id.':'.$exportId;
        $key = app(OwnershipBoundary::class)->key('forms.forms', $identity);
        expect(Cache::get($key))->toBe(['status' => 'completed', 'progress' => 100]);
    });
});

it('limits explicit retention to the admitted Form owner and denies foreign preloaded Forms', function (): void {
    config(['nvl-forms.retention.enabled' => true, 'nvl-forms.retention.authorization_ability' => 'retain-form-data', 'nvl-forms.retention.entries_days' => 30]);
    Gate::define('retain-form-data', static fn (): bool => true);
    $forms = [];
    foreach ([7, 8] as $id) {
        $forms[$id] = $this->owned($id, function () use ($id): OwnershipForm {
            $form = $this->createOwnedForm();
            FormEntry::factory()->for($form, 'form')->create(['email' => 'retention-'.$id.'@example.test', 'created_at' => now()->subDays(60)]);

            return $form;
        });
    }
    $this->owned(7, function () use ($forms): void {
        $actor = Actor::firstOrFail();
        expect(fn () => app(FormRetention::class)->prune($forms[8], $actor))->toThrow(OwnershipViolation::class);
        expect(app(FormRetention::class)->prune($forms[7], $actor)['entries'])->toBe(1)
            ->and(FormEntry::query()->count())->toBe(0);
    });
    $this->owned(8, function (): void {
        expect(FormEntry::query()->count())->toBe(1);
    });
});
