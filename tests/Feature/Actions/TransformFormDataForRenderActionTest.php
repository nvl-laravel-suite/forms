<?php

declare(strict_types=1);
use Nvl\Forms\Models\Form;
use Nvl\Forms\Tests\Fixtures\Consumer\Data\Display\PublicFormRenderPayload;
use Nvl\Forms\Tests\Fixtures\Consumer\Presentation\TransformFormDataForRenderAction;

test('transform form data for render action maps essentials', function (): void {
    $form = Form::factory()->create(['last_used_at' => now()]);
    $payload = app(TransformFormDataForRenderAction::class)->execute($form);
    expect($payload)->toBeInstanceOf(PublicFormRenderPayload::class)->and($payload->id)->toBe($form->id)->and($payload->name)->toBe($form->displayName())->and($payload->toArray())->not->toHaveKeys(['submissionsCount', 'lastUsedAt']);
});
