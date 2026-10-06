<?php

declare(strict_types=1);

use Nvl\Forms\Services\FormsDoctor;
use Nvl\Support\Doctor\DoctorRegistry;
use Nvl\Support\Providers\DoctorServiceProvider;

it('contributes the same package-owned checks to the consumer Doctor', function (): void {
    $this->app->register(DoctorServiceProvider::class);
    $expected = array_map(static fn ($check): string => $check->key, $this->app->make(FormsDoctor::class)->inspect());
    sort($expected);
    $report = $this->app->make(DoctorRegistry::class)->inspect();
    $actual = array_column(array_values(array_filter($report['checks'], static fn (array $check): bool => $check['package'] === 'nvl/forms')), 'key');

    expect($actual)->toBe($expected);
});

test('forms doctor reports a healthy standalone installation', function (): void {
    $checks = collect(app(FormsDoctor::class)->inspect());

    expect($checks)->not->toBeEmpty()
        ->and($checks->every(
            static fn (object $check): bool => $check->passed === true,
        ))->toBeTrue();

    $this->artisan('nvl:forms:doctor', [
        '--strict' => true,
        '--format' => 'json',
    ])->assertSuccessful();
});

test('forms doctor rejects enabled management routes without a registered gate', function (): void {
    config(['nvl-forms.authorization.gate' => 'missing-forms-gate']);

    $check = collect(app(FormsDoctor::class)->inspect())
        ->firstWhere('key', 'authorization.management');

    expect($check)->not->toBeNull()
        ->and($check->passed)->toBeFalse();
});

test('forms doctor rejects enabled public routes without throttling', function (): void {
    config(['nvl-forms.routes.public.middleware' => ['api']]);

    $check = collect(app(FormsDoctor::class)->inspect())
        ->firstWhere('key', 'routes.public.throttle');

    expect($check)->not->toBeNull()
        ->and($check->passed)->toBeFalse();
});

test('forms doctor rejects a malformed public token signing key', function (): void {
    config(['app.key' => 'base64:not-valid-base64***']);

    $check = collect(app(FormsDoctor::class)->inspect())
        ->firstWhere('key', 'security.application_key');

    expect($check)->not->toBeNull()
        ->and($check->passed)->toBeFalse();
});
