<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Nvl\Forms\Actions\Form\GetFormForRenderAction;
use Nvl\Forms\Models\FormEntry;
use Nvl\Forms\Tests\Fixtures\OwnershipForm;
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
