<?php

declare(strict_types=1);
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Support\FormErrorMapperRegistry;
use Nvl\Forms\Tests\Fixtures\Consumer\Presentation\HandleFormSubmissionErrorAction;
use Nvl\Forms\Tests\Fixtures\Consumer\Services\PublicFormSubmissionResponseMapper;
use Nvl\Support\Exceptions\BusinessException;

test('handle form submission error action maps host errors', function (): void {
    $action = app(HandleFormSubmissionErrorAction::class);
    $message = $action->execute(new Exception('Submission not allowed from this host'));
    expect($message)->toBe(trans('nvl-forms::forms/messages.error.host_not_allowed'));
});
test('handle form submission error action falls back to generic message', function (): void {
    $action = app(HandleFormSubmissionErrorAction::class);
    $message = $action->execute(new Exception('Unexpected failure'));
    expect($message)->toBe(trans('nvl-forms::forms/messages.error.submission_failed'));
});
it('uses the supplied translator and safe native metadata in both submission adapters', function (): void {
    $loader = new ArrayLoader;
    $loader->addMessages('en', 'responsecode', ['operation_failed' => 'Host supplied safe message'], 'nvl-core');
    $translator = new Translator($loader, 'en');
    $failure = new BusinessException('private diagnostic');
    $action = new HandleFormSubmissionErrorAction($this->app, $translator);
    $mapper = new PublicFormSubmissionResponseMapper(new FormErrorMapperRegistry($this->app), $action);
    $form = Form::factory()->make(['handle' => 'unmapped']);
    expect($action->execute($failure))->toBe('Host supplied safe message')->and($mapper->businessErrors($form, $failure))->toBe(['error' => 'Host supplied safe message']);
});
