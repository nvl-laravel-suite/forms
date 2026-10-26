<?php

declare(strict_types=1);
use Nvl\Forms\Models\Form;
use Nvl\Forms\Tests\Fixtures\Consumer\Presentation\FormNavigation;

test('get form navigation action returns overview tab with expected href', function (): void {
    $form = Form::factory()->create();
    $navigation = app(FormNavigation::class)->navigation($form, true);
    expect($navigation)->toBeArray()->and($navigation)->toHaveCount(1);
    $tab = $navigation[0];
    expect($tab['label'] ?? null)->toBe(trans('nvl-forms::forms/general.tabs.overview'))->and($tab['href'] ?? null)->toBe("/forms/{$form->id}")->and($tab['active'] ?? null)->toBeTrue();
    $inactive = app(FormNavigation::class)->navigation($form, false);
    expect($inactive[0]['active'] ?? null)->toBeFalse();
});
