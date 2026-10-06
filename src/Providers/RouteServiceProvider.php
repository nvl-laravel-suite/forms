<?php

declare(strict_types=1);

namespace Nvl\Forms\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Nvl\Forms\Support\FormsConfiguration;
use Nvl\Support\Globals\GlobalNames;
use Nvl\Support\Traits\RegistersNamespacedResources;

final class RouteServiceProvider extends ServiceProvider
{
    use RegistersNamespacedResources;

    protected string $name = 'Forms';

    /**
     * Called before routes are registered.
     * Register any model bindings or pattern based filters.
     */
    public function boot(): void
    {
        $this->map();
        if (config('nvl-forms.routes.public.enabled', false) !== true) {
            return;
        }

        $limiter = static function (Request $request): Limit {
            $maxAttempts = FormsConfiguration::positiveInteger(
                'nvl-forms.security.rate_limit.max_attempts',
                10,
            );
            $decayMinutes = FormsConfiguration::positiveInteger(
                'nvl-forms.security.rate_limit.decay_minutes',
                1,
            );

            return Limit::perMinute($maxAttempts, $decayMinutes)
                ->by($request->user()?->id ?: $request->ip());
        };
        $names = $this->app->make(GlobalNames::class);
        $exists = static fn (string $name): bool => RateLimiter::limiter($name) !== null;
        $install = static function (string $name) use ($limiter): void {
            RateLimiter::for($name, $limiter);
        };
        $names->reserve('forms', 'limiter', 'nvl.forms.public', $exists, $install);
        $names->register('forms', 'limiter', 'forms-public', 'nvl.forms.public', $exists, $install);
    }

    /**
     * Define the routes for the application.
     */
    public function map(): void
    {
        $this->mapApiRoutes();
        $this->mapWebRoutes();
    }

    /**
     * Define the "web" routes for the application.
     * These routes all receive session state, CSRF protection, etc.
     */
    protected function mapWebRoutes(): void {}

    /**
     * Define the "api" routes for the application.
     * These routes are typically stateless.
     */
    protected function mapApiRoutes(): void
    {
        if (! (bool) config('nvl-forms.routes.management.enabled', false)
            && ! (bool) config('nvl-forms.routes.public.enabled', false)) {
            return;
        }

        Route::middleware($this->middleware())
            ->prefix(trim(FormsConfiguration::string('nvl-forms.routes.prefix', 'nvl/api/v1'), '/'))
            ->group(function (): void {
                $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
            });
    }

    /**
     * @return list<string>
     */
    private function middleware(): array
    {
        return array_values(array_filter(
            (array) config('nvl-forms.routes.middleware', ['api']),
            static fn (mixed $middleware): bool => is_string($middleware) && $middleware !== '',
        ));
    }
}
