<?php

declare(strict_types=1);

namespace Nvl\Forms\Providers;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Nvl\Data\Services\TypeScriptSourceRegistry;
use Nvl\Forms\Actions\Form\CreateFormAction;
use Nvl\Forms\Actions\FormEntry\CreateFormEntryAction;
use Nvl\Forms\Console\Commands\FormsDoctorCommand;
use Nvl\Forms\Contracts\CreateFormContract;
use Nvl\Forms\Contracts\CreateFormEntryContract;
use Nvl\Forms\Contracts\FormEntryDeletionPolicy;
use Nvl\Forms\Contracts\FormEntryPrivacyPolicy;
use Nvl\Forms\Contracts\FormRateLimiter;
use Nvl\Forms\Contracts\FormSpamDetector;
use Nvl\Forms\Http\Middleware\EnsureFormIsAvailable;
use Nvl\Forms\Http\Middleware\FormsLocaleMiddleware;
use Nvl\Forms\Http\Middleware\ValidateFormHost;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormEntry;
use Nvl\Forms\Policies\FormEntryPolicy;
use Nvl\Forms\Policies\FormPolicy;
use Nvl\Forms\Services\AllowFormEntryDeletion;
use Nvl\Forms\Services\AllowFormEntryPrivacyOperations;
use Nvl\Forms\Services\EntryCallbackRegistry;
use Nvl\Forms\Services\FormRateLimitService;
use Nvl\Forms\Services\FormsDoctor;
use Nvl\Forms\Services\FormSpamDetectionService;
use Nvl\Forms\Support\FormErrorMapperRegistry;
use Nvl\Forms\Support\FormHandlerRegistry;
use Nvl\Forms\Support\FormRenderDataRegistry;
use Nvl\Forms\Tenancy\FormsResourceRegistrar;
use Nvl\Support\Doctor\PackageDoctorContributor;
use Nvl\Support\Globals\GlobalNames;
use Nvl\Support\Providers\TenantServiceProvider;
use Nvl\Support\Tenancy\Services\TenantResourceRegistry;
use Nvl\Support\Traits\MergesPackageConfiguration;
use Nvl\Support\Traits\RegistersNamespacedResources;
use Nvl\Tenancy\Services\TenantAdoptionRegistry;
use Nvl\Translatable\Services\TranslationResourceRegistry;

/**
 * Service provider for the Forms module.
 */
final class FormsServiceProvider extends ServiceProvider
{
    use MergesPackageConfiguration;
    use RegistersNamespacedResources;

    protected string $name = 'Forms';

    protected string $nameLower = 'forms';

    /**
     * Boot the application events.
     *
     * @throws BindingResolutionException
     */
    public function boot(
        TranslationResourceRegistry $translationResources,
        TypeScriptSourceRegistry $typeScriptSources,
    ): void {
        $typeScriptSources->register(__DIR__.'/..', 'nvl/forms');

        $this->publishes([
            __DIR__.'/../../resources/boost/skills' => base_path('.agents/skills'),
        ], 'forms-skills');
        $this->publishesMigrations([
            __DIR__.'/../../database/migrations' => database_path('migrations'),
        ], 'forms-migrations');

        if ($this->app->runningInConsole()) {
            $this->registerCommands();
        }

        $this->registerPolicies();
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerMiddleware();
        $this->registerRegistries();
        if ((bool) config('nvl-forms.migrations.enabled', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        }

        $translationResources->register(
            key: 'forms.forms',
            modelClass: Form::class,
            label: 'Forms',
            searchableColumns: ['handle'],
            displayColumns: ['handle', 'status'],
            orderColumn: 'created_at',
        );
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        PackageDoctorContributor::register($this->app, 'nvl/forms', fn (): array => $this->app->make(FormsDoctor::class)->inspect());

        $this->app->register(TenantServiceProvider::class);
        $this->mergePackageConfiguration(__DIR__.'/../../config/nvl-forms.php', 'forms');
        (new FormsResourceRegistrar)->register($this->app->make(TenantResourceRegistry::class));
        $this->app->booted(function (): void {
            if ($this->app->bound(TenantAdoptionRegistry::class)) {
                (new FormsResourceRegistrar)->register($this->app->make(TenantResourceRegistry::class), $this->app->make(TenantAdoptionRegistry::class));
            }
        });

        $this->app->singletonIf(FormHandlerRegistry::class, fn (): FormHandlerRegistry => new FormHandlerRegistry);
        $this->app->singletonIf(EntryCallbackRegistry::class, fn (Container $app): EntryCallbackRegistry => new EntryCallbackRegistry($app));
        $this->app->singletonIf(FormRenderDataRegistry::class, fn (Container $app): FormRenderDataRegistry => new FormRenderDataRegistry($app));
        $this->app->singletonIf(FormErrorMapperRegistry::class, fn (Container $app): FormErrorMapperRegistry => new FormErrorMapperRegistry($app));
        $this->app->scoped(FormRateLimiter::class, FormRateLimitService::class);
        $this->app->singletonIf(FormEntryDeletionPolicy::class, AllowFormEntryDeletion::class);
        $this->app->singletonIf(FormEntryPrivacyPolicy::class, AllowFormEntryPrivacyOperations::class);
        $this->app->scoped(FormSpamDetector::class, FormSpamDetectionService::class);
        $this->app->bind(CreateFormContract::class, CreateFormAction::class);
        $this->app->bind(CreateFormEntryContract::class, CreateFormEntryAction::class);
        $this->app->register(RouteServiceProvider::class);
    }

    /**
     * Register commands in the format of Command::class
     */
    protected function registerCommands(): void
    {
        $this->commands([
            FormsDoctorCommand::class,
        ]);
    }

    /**
     * Register model policies for the module.
     */
    protected function registerPolicies(): void
    {
        Gate::policy(Form::class, FormPolicy::class);
        Gate::policy(FormEntry::class, FormEntryPolicy::class);
    }

    /**
     * Register middleware.
     *
     * @throws BindingResolutionException
     */
    protected function registerMiddleware(): void
    {
        if (config('nvl-forms.routes.public.enabled', false) !== true) {
            return;
        }
        $router = $this->app->make(Router::class);
        $names = $this->app->make(GlobalNames::class);
        foreach (['validate-form-host' => ['nvl.forms.validate-host', ValidateFormHost::class],
            'forms-locale' => ['nvl.forms.locale', FormsLocaleMiddleware::class],
            'form-available' => ['nvl.forms.available', EnsureFormIsAvailable::class]] as $legacy => [$canonical, $middleware]) {
            $exists = static fn (string $name): bool => array_key_exists($name, $router->getMiddleware());
            $install = static function (string $name) use ($router, $middleware): void {
                $router->aliasMiddleware($name, $middleware);
            };
            $names->reserve('forms', 'middleware', $canonical, $exists, $install);
            $names->register('forms', 'middleware', $legacy, $canonical, $exists, $install);
        }
    }

    /**
     * Register default registry bindings for the module.
     *
     * Consuming modules register their own callbacks in their service providers
     * via EntryCallbackRegistry for cleaner ownership boundaries.
     */
    protected function registerRegistries(): void
    {
        // Intentionally empty — callbacks are registered by owning modules
    }

    /**
     * Register translations.
     */
    public function registerTranslations(): void
    {
        $langPath = __DIR__.'/../../lang';

        $this->app->make(GlobalNames::class)->translations('forms', $langPath, $this->app->make('translation.loader'));

        $this->publishes([
            $langPath => lang_path('vendor/nvl-'.$this->nameLower),
        ], 'forms-translations');
    }

    /**
     * Register config.
     */
    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__.'/../../config/nvl-forms.php' => config_path('nvl-forms.php'),
        ], 'forms-config');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            FormRateLimiter::class,
            FormHandlerRegistry::class,
            EntryCallbackRegistry::class,
            FormRenderDataRegistry::class,
            FormErrorMapperRegistry::class,
        ];
    }
}
