<?php

declare(strict_types=1);
use Nvl\Forms\Exceptions\FormSubmissionRejectionException;
use Nvl\Forms\Models\AllowedOrigin;
use Nvl\Forms\Models\Form;

test('validate form host access passes when public access is unrestricted', function (): void {
    $form = Form::factory()->create(['restrict_public_access' => false]);
    formSubmissionStage('validateHost', $form, null);
    expect(true)->toBeTrue();
});
test('validate form host access ensures provided origin is allowed', function (): void {
    $form = Form::factory()->create(['restrict_public_access' => true]);
    AllowedOrigin::factory()->for($form)->create(['origin' => '*.example.com']);
    formSubmissionStage('validateHost', $form, 'https://shop.example.com');
    expect(true)->toBeTrue();
});
test('validate form host access throws when origin missing under restriction', function (): void {
    $form = Form::factory()->create(['restrict_public_access' => true]);
    $this->expectException(FormSubmissionRejectionException::class);
    $this->expectExceptionMessage(trans('nvl-forms::forms/messages.error.origin_required'));
    formSubmissionStage('validateHost', $form, null);
});
