<?php

declare(strict_types=1);

use Nvl\Forms\Actions\FormEntry\ValidateFormEntryOwnershipAction;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormEntry;

test('validate form entry ownership action allows matching form and entry', function (): void {
    $form = Form::factory()->create();
    $entry = FormEntry::factory()->for($form)->create();

    expect(fn () => app(ValidateFormEntryOwnershipAction::class)->execute($form, $entry))->not->toThrow(Exception::class);
});

test('validate form entry ownership action throws for mismatched form', function (): void {
    $form = Form::factory()->create();
    $entry = FormEntry::factory()->create();

    $this->expectException(Exception::class);
    $this->expectExceptionMessage(trans('nvl-forms::forms/shared.messages.error.ownership_mismatch', [
        'item' => trans('nvl-forms::entries/general.entities.singular'),
        'parent' => trans('nvl-forms::forms/general.entities.singular'),
    ]));

    app(ValidateFormEntryOwnershipAction::class)->execute($form, $entry);
});
