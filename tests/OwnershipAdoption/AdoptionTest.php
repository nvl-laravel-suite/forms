<?php

declare(strict_types=1);

use Nvl\Forms\Models\FormEntry;
use Nvl\Forms\Models\FormSubmissionReceipt;
use Nvl\Forms\Services\FormModelRegistry;
use Nvl\Forms\Tests\LegacyOwnershipTestCase;

it('preserves reviewed form IDs locale copy entries and completed receipts during adoption', function (): void {
    $this->owned(7, function (): void {
        $form = FormModelRegistry::query()->findOrFail(LegacyOwnershipTestCase::FORM);
        expect($form->displayName('en'))->toBe('Legacy contact')
            ->and($form->entries->first()->email)->toBe('legacy@example.test')
            ->and(FormSubmissionReceipt::firstOrFail()->result_id)->toBe('legacy-result');
    });
    $this->owned(8, fn () => expect(FormEntry::count())->toBe(0));
});
