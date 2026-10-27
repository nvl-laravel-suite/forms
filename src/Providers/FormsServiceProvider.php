<?php

declare(strict_types=1);

namespace Nvl\Forms\Providers;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Nvl\Forms\Console\Commands\FormsDoctorCommand;
use Nvl\Forms\Contracts\FormEntryDeletionPolicy;
use Nvl\Forms\Contracts\FormEntryPrivacyPolicy;
use Nvl\Forms\Contracts\FormRateLimiter;
use Nvl\Forms\Contracts\FormSpamDetector;
use Nvl\Forms\Models\FormEntry;
use Nvl\Forms\Ownership\FormsAdoptionAdapter;
use Nvl\Forms\Ownership\FormsResourceRegistrar as OwnershipFormsResourceRegistrar;
use Nvl\Forms\Policies\FormEntryPolicy;
use Nvl\Forms\Policies\FormPolicy;
use Nvl\Forms\Services\AllowFormEntryDeletion;
use Nvl\Forms\Services\AllowFormEntryPrivacyOperations;
use Nvl\Forms\Services\EntryCallbackRegistry;
use Nvl\Forms\Services\FormModelRegistry;
use Nvl\Forms\Services\FormRateLimitService;
use Nvl\Forms\Services\FormsDoctor;
use Nvl\Forms\Services\FormSpamDetectionService;
use Nvl\Forms\Support\FormErrorMapperRegistry;
use Nvl\Forms\Support\FormHandlerRegistry;
use Nvl\Forms\Support\FormRenderDataRegistry;
use Nvl\Ownership\Services\OwnershipAdoptionRegistry;
use Nvl\Support\Doctor\PackageDoctorContributor;
use Nvl\Support\Globals\GlobalNames;
use Nvl\Support\Ownership\Contracts\OwnershipIntegratedProvider;
use Nvl\Support\Ownership\Services\OwnershipResourceRegistry;
use Nvl\Support\Traits\MergesPackageConfiguration;
use Nvl\Support\Traits\RegistersNamespacedResources;
use Nvl\Translatable\Services\TranslationResourceRegistry;

/**
 * Service provider for the Forms module.
 */
final class FormsServiceProvider extends ServiceProvider implements OwnershipIntegratedProvider
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
    ): void {
        $this->app->make(OwnershipFormsResourceRegistrar::class)->register($this->app->make(OwnershipResourceRegistry::class));
        if ($this->app->bound(OwnershipAdoptionRegistry::class)) {
            $this->app->make(OwnershipAdoptionRegistry::class)->register('forms', FormsAdoptionAdapter::class);
        }

        $this->publishes([
            __DIR__.'/../../resources/boost/skills' => base_path('.agents/skills'),
        ], 'forms-skills');
        $migrations = array_values(array_filter(
            glob(__DIR__.'/../../database/migrations/*.php') ?: [],
            static fn (string $path): bool => basename($path) !== '2026_07_29_055330_nvl_forms_create_form_submission_receipts_table.php',
        ));
        $migrations[] = __DIR__.'/../../database/migrations/current/2026_07_29_055330_nvl_forms_create_form_submission_receipts_table.php';
        $publishedMigrations = [];
        foreach ($migrations as $path) {
            $publishedMigrations[$path] = database_path('migrations/'.basename($path));
        }
        $this->publishesMigrations($publishedMigrations, 'forms-migrations');

        if ($this->app->runningInConsole()) {
            $this->registerCommands();
        }

        $this->registerPolicies();
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerRegistries();
        if ((bool) config('nvl-forms.migrations.enabled', true)) {
            $this->loadMigrationsFrom($migrations);
        }

        $translationResources->register(
            key: 'forms.forms',
            modelClass: FormModelRegistry::modelClass(),
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

        $this->mergePackageConfiguration(__DIR__.'/../../config/nvl-forms.php', 'forms');
        $this->app->singleton(FormModelRegistry::class);
        $this->app->booting(fn () => $this->app->make(FormModelRegistry::class)->prepare());

        $this->app->singletonIf(FormHandlerRegistry::class, fn (): FormHandlerRegistry => new FormHandlerRegistry);
        $this->app->singletonIf(EntryCallbackRegistry::class, fn (Container $app): EntryCallbackRegistry => new EntryCallbackRegistry($app));
        $this->app->singletonIf(FormRenderDataRegistry::class, fn (Container $app): FormRenderDataRegistry => new FormRenderDataRegistry($app));
        $this->app->singletonIf(FormErrorMapperRegistry::class, fn (Container $app): FormErrorMapperRegistry => new FormErrorMapperRegistry($app));
        $this->app->scopedIf(FormRateLimiter::class, FormRateLimitService::class);
        $this->app->singletonIf(FormEntryDeletionPolicy::class, AllowFormEntryDeletion::class);
        $this->app->singletonIf(FormEntryPrivacyPolicy::class, AllowFormEntryPrivacyOperations::class);
        $this->app->scopedIf(FormSpamDetector::class, FormSpamDetectionService::class);
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
        Gate::policy(FormModelRegistry::modelClass(), FormPolicy::class);
        Gate::policy(FormEntry::class, FormEntryPolicy::class);
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
