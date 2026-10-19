<?php

declare(strict_types=1);

use Nvl\Forms\Actions\Form\DuplicateFormAction;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Tests\Fixtures\OwnershipForm;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;

it('uses the configured native form root for creation localized copy and relations', function (): void {
    $this->owned(7, function (): void {
        $form = $this->createOwnedForm();
        expect($form)->toBeInstanceOf(OwnershipForm::class)
            ->and($form->translations->first()->form)->toBeInstanceOf(OwnershipForm::class);
        expect(fn () => Form::count())->toThrow(OwnershipViolation::class);
    });
});

it('creates ordinary host test factories and duplicates through the same primary assignment', function (): void {
    $this->owned(7, function (): void {
        $fixture = OwnershipForm::factory()->create(['handle' => 'factory-contact']);
        expect($fixture)->toBeInstanceOf(OwnershipForm::class)->and($fixture->fresh()->company_id)->toBe(7);
        $form = $this->createOwnedForm();
        $copy = app(DuplicateFormAction::class)->execute($form);
        expect($copy)->toBeInstanceOf(OwnershipForm::class)->and($copy->fresh()->company_id)->toBe(7)
            ->and($copy->id)->not->toBe($form->id)->and($copy->submissions_count)->toBe(0);
    });
});
