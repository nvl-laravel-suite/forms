<?php

declare(strict_types=1);
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Nvl\Forms\Contracts\FormRateLimiter;
use Nvl\Forms\Contracts\FormSpamDetector;
use Nvl\Forms\Providers\FormsServiceProvider;

test('distributed route defaults are disabled independently', function (): void {
    /** @var array<string, mixed> $defaults */
    $defaults = require __DIR__.'/../../config/nvl-forms.php';
    expect(data_get($defaults, 'routes.management.enabled'))->toBeNull()->and(data_get($defaults, 'routes.public.enabled'))->toBeNull()->and(data_get($defaults, 'routes.prefix'))->toBeNull();
});
test('consumer configuration wins while omitted nested package defaults remain available', function (): void {
    config()->set('nvl-forms', ['routes' => ['prefix' => 'consumer/forms'], 'security' => ['rate_limit' => ['max_attempts' => 7]]]);
    (new FormsServiceProvider(app()))->register();
    expect(config('nvl-forms.routes.prefix'))->toBe('consumer/forms')->and(config('nvl-forms.routes.management.enabled'))->toBeNull()->and(config('nvl-forms.routes.public.enabled'))->toBeNull()->and(config('nvl-forms.security.rate_limit.max_attempts'))->toBe(7)->and(config('nvl-forms.security.rate_limit.decay_minutes'))->toBe(1);
});
test('the public route limiter uses the documented consumer configuration', function (): void {
    config()->set('nvl-forms.security.rate_limit.max_attempts', 7);
    config()->set('nvl-forms.security.rate_limit.decay_minutes', 2);
    $limiter = RateLimiter::limiter('nvl.forms.public');
    expect($limiter)->toBeCallable();
    $limit = $limiter(Request::create('/forms', 'POST'));
    expect($limit->maxAttempts)->toBe(7)->and($limit->decaySeconds)->toBe(120);
});

test('provider registration preserves consumer scoped security bindings', function (string $contract): void {
    app()->scoped($contract, static fn () => Mockery::mock($contract));
    $consumer = app($contract);

    (new FormsServiceProvider(app()))->register();

    expect(app($contract))->toBe($consumer);
    app()->forgetScopedInstances();
    expect(app($contract))->not->toBe($consumer);
})->with([FormRateLimiter::class, FormSpamDetector::class]);

test('native form security defaults retain their scoped lifecycle', function (string $contract): void {
    $first = app($contract);
    expect(app($contract))->toBe($first);

    app()->forgetScopedInstances();

    expect(app($contract))->not->toBe($first);
})->with([FormRateLimiter::class, FormSpamDetector::class]);
