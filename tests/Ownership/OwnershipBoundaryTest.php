<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Nvl\Forms\Actions\Form\GetFormForRenderAction;
use Nvl\Forms\Actions\FormEntry\ExportFormEntriesAction;
use Nvl\Forms\Models\FormEntry;
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
        expect(OwnershipForm::count())->toBe(1)->and(app(GetFormForRenderAction::class)->execute('contact')->id)->toBe($forms[7]->id);
        expect(fn () => app(GetFormForRenderAction::class)->execute($forms[8]))->toThrow(ModelNotFoundException::class)
            ->and(fn () => $forms[8]->saveQuietly())->toThrow(OwnershipViolation::class)
            ->and(fn () => FormEntry::create(['form_id' => $forms[8]->id, 'email' => 'foreign@example.test']))->toThrow(OwnershipViolation::class);
    });
});

it('enforces inherited native scope for raw submission writes', function (): void {
    $a = $this->owned(7, fn () => $this->createOwnedForm());
    $b = $this->owned(8, fn () => $this->createOwnedForm());
    $entry = new FormEntry;
    expect(fn () => $entry->getConnection()->table($entry->getTable())->insert([
        'id' => (string) Str::uuid(), 'form_id' => $b->id,
        'owner_scope_key' => $a->getRawOriginal('owner_scope_key'), 'submitted_from' => 'https://example.test',
    ]))->toThrow(QueryException::class);
});

it('partitions export artifacts progress and cleanup before any foreign Form effect', function (): void {
    Storage::fake('local');
    config(['nvl-forms.authorization.gate' => 'authoritative-group']);
    Gate::define('authoritative-group', static fn (): bool => true);
    $forms = [];
    $paths = [];
    foreach ([8, 7] as $id) {
        $forms[$id] = $this->owned($id, fn () => $this->createOwnedForm());
        $paths[$id] = $this->owned($id, function () use ($forms, $id): string {
            FormEntry::factory()->for($forms[$id], 'form')->create(['email' => 'export-'.$id.'@example.test']);

            return app(ExportFormEntriesAction::class)->execute($forms[$id], [], Actor::firstOrFail());
        });
        touch($paths[$id], now()->subHours(2)->timestamp);
    }
    expect(file_exists($paths[8]))->toBeTrue()
        ->and(dirname($paths[7]))->not->toBe(dirname($paths[8]));
    $this->owned(7, function () use ($forms, $paths): void {
        expect(fn () => app(ExportFormEntriesAction::class)->execute($forms[8], [], Actor::firstOrFail()))
            ->toThrow(OwnershipViolation::class);
        expect(file_exists($paths[8]))->toBeTrue()->and(file_exists($paths[7]))->toBeTrue();
        $path = app(ExportFormEntriesAction::class)->execute($forms[7], [], Actor::firstOrFail());
        expect(file_exists($paths[7]))->toBeFalse()->and(file_exists($paths[8]))->toBeTrue();
        $exportId = explode('_', basename($path), 2)[0];
        $identity = 'nvl:forms:export-progress:1:'.$forms[7]->id.':'.$exportId;
        $key = app(OwnershipBoundary::class)->key('forms.forms', $identity);
        expect(Cache::get($key))->toBe(['status' => 'completed', 'progress' => 100]);
    });
});
