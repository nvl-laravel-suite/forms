<?php

declare(strict_types=1);
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Nvl\Forms\Enums\FormStatus;
use Nvl\Forms\Enums\FormType;
use Nvl\Forms\Enums\Resolvement;
use Nvl\Forms\Tests\Fixtures\Consumer\Data\Mutations\MutateFormPayload;

test('form mutation data accepts normalized host-only allowed origins', function (): void {
    $payload = ['translations' => ['en' => ['name' => 'Public form']], 'resolvement' => Resolvement::ENTRIES->value, 'type' => FormType::LANDING_PAGE->value, 'status' => FormStatus::ACTIVE->value, 'allowedOrigins' => ['example.com', '*.example.com', 'example.com:8443', 'example.com/*', 'localhost:5173']];
    $validator = Validator::make($payload, MutateFormPayload::rulesForCreate());
    expect($validator->passes())->toBeTrue();
});
test('form mutation data rejects invalid allowed origin expressions', function (string $origin): void {
    $payload = ['translations' => ['en' => ['name' => 'Public form']], 'resolvement' => Resolvement::ENTRIES->value, 'type' => FormType::LANDING_PAGE->value, 'status' => FormStatus::ACTIVE->value, 'allowedOrigins' => [$origin]];
    $validator = Validator::make($payload, MutateFormPayload::rulesForCreate());
    expect($validator->fails())->toBeTrue();
})->with(['scheme' => ['https://example.com'], 'arbitrary path' => ['example.com/path'], 'comma' => ['example.com,evil.com'], 'backslash' => ['example.com\evil'], 'root wildcard' => ['*.com'], 'invalid port' => ['example.com:0']]);

test('native consumer mutation validation preserves sparse nulls nested content and enums', function (): void {
    $payload = [
        'expected_revision' => 2,
        'status' => FormStatus::ACTIVE,
        'availableUntil' => null,
        'allowedOrigins' => null,
        'translations' => ['en' => ['description' => null, 'fields' => ['servicePlan' => ['label' => 'Plan', 'defaultValue' => null]]]],
        'options' => ['showLogo' => false, 'accentColor' => null, 'consumerOptions' => ['orderCode' => 'ABC123']],
    ];

    $validated = MutateFormPayload::validateForUpdate($payload + ['untrusted' => true], 'unused-server-form-id');

    expect($validated)->toBe($payload)
        ->not->toHaveKeys(['handle', 'enableHoneypot', 'translation_mode', 'untrusted']);
});

test('native consumer create validation retains localized fields and explicit false options', function (): void {
    $payload = [
        'translations' => ['en' => ['name' => 'Signup', 'fields' => ['email' => ['label' => 'Email']]]],
        'resolvement' => Resolvement::ENTRIES,
        'type' => FormType::IFRAME,
        'restrictPublicAccess' => false,
        'corsSettings' => ['policy' => 'strict', 'allowCredentials' => false],
    ];

    expect(MutateFormPayload::validateForCreate($payload))->toBe($payload);
    expect(fn () => MutateFormPayload::validateForCreate(['status' => 'invalid']))->toThrow(ValidationException::class);
});
