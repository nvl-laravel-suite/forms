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
use Nvl\Forms\Actions\Form\DeleteFormAction;
use Nvl\Forms\Actions\Form\DuplicateFormAction;
use Nvl\Forms\Actions\Form\GetFormAnalyticsBundleAction;
use Nvl\Forms\Actions\Form\GetFormAnalyticsSummaryAction;
use Nvl\Forms\Actions\Form\GetFormForRenderAction;
use Nvl\Forms\Actions\Form\GetFormSelectOptionsAction;
use Nvl\Forms\Actions\Form\GetFormSuggestionsAction;
use Nvl\Forms\Actions\Form\GetFormValidationSchemaAction;
use Nvl\Forms\Actions\Form\HandlePublicFormSubmissionAction;
use Nvl\Forms\Actions\Form\ListFormsAction;
use Nvl\Forms\Actions\Form\SearchFormsAction;
use Nvl\Forms\Actions\Form\ShowFormAction;
use Nvl\Forms\Actions\Form\UpdateFormAction;
use Nvl\Forms\Actions\FormEntry\AnonymizeFormEntryAction;
use Nvl\Forms\Actions\FormEntry\CreateFormEntryAction;
use Nvl\Forms\Actions\FormEntry\DeleteFormEntryAction;
use Nvl\Forms\Actions\FormEntry\ExportFormEntriesAction;
use Nvl\Forms\Actions\FormEntry\ListFormEntriesAction;
use Nvl\Forms\Actions\FormEntry\MarkFormEntryAsLegitimateAction;
use Nvl\Forms\Actions\FormEntry\MarkFormEntryAsSpamAction;
use Nvl\Forms\Actions\FormEntry\RedactFormEntryAction;
use Nvl\Forms\Actions\FormEntry\ShowFormEntryAction;
use Nvl\Forms\Console\Commands\FormsDoctorCommand;
use Nvl\Forms\Contracts\AnonymizeFormEntryContract;
use Nvl\Forms\Contracts\CreateFormContract;
use Nvl\Forms\Contracts\CreateFormEntryContract;
use Nvl\Forms\Contracts\DeleteFormContract;
use Nvl\Forms\Contracts\DeleteFormEntryContract;
use Nvl\Forms\Contracts\DuplicateFormContract;
use Nvl\Forms\Contracts\ExportFormEntriesContract;
use Nvl\Forms\Contracts\FormEntryDeletionPolicy;
use Nvl\Forms\Contracts\FormEntryPrivacyPolicy;
use Nvl\Forms\Contracts\FormRateLimiter;
use Nvl\Forms\Contracts\FormSpamDetector;
use Nvl\Forms\Contracts\GetFormAnalyticsBundleContract;
use Nvl\Forms\Contracts\GetFormAnalyticsSummaryContract;
use Nvl\Forms\Contracts\GetFormForRenderContract;
use Nvl\Forms\Contracts\GetFormSelectOptionsContract;
use Nvl\Forms\Contracts\GetFormSuggestionsContract;
use Nvl\Forms\Contracts\GetFormValidationSchemaContract;
use Nvl\Forms\Contracts\HandlePublicFormSubmissionContract;
use Nvl\Forms\Contracts\ListFormEntriesContract;
use Nvl\Forms\Contracts\ListFormsContract;
use Nvl\Forms\Contracts\MarkFormEntryAsLegitimateContract;
use Nvl\Forms\Contracts\MarkFormEntryAsSpamContract;
use Nvl\Forms\Contracts\RedactFormEntryContract;
use Nvl\Forms\Contracts\SearchFormsContract;
use Nvl\Forms\Contracts\ShowFormContract;
use Nvl\Forms\Contracts\ShowFormEntryContract;
use Nvl\Forms\Contracts\UpdateFormContract;
use Nvl\Forms\Events\FormChanged;
use Nvl\Forms\Events\FormChangedEvent;
use Nvl\Forms\Events\FormEntryChanged;
use Nvl\Forms\Events\FormEntryChangedEvent;
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
use Nvl\Support\Events\EventAliases;
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
        $this->app->make(EventAliases::class)->register(FormChanged::class, FormChangedEvent::class);
        $this->app->make(EventAliases::class)->register(FormEntryChanged::class, FormEntryChangedEvent::class);
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
        $this->app->bindIf(AnonymizeFormEntryContract::class, AnonymizeFormEntryAction::class);
        $this->app->bindIf(DeleteFormEntryContract::class, DeleteFormEntryAction::class);
        $this->app->bindIf(ExportFormEntriesContract::class, ExportFormEntriesAction::class);
        $this->app->bindIf(ListFormEntriesContract::class, ListFormEntriesAction::class);
        $this->app->bindIf(MarkFormEntryAsLegitimateContract::class, MarkFormEntryAsLegitimateAction::class);
        $this->app->bindIf(MarkFormEntryAsSpamContract::class, MarkFormEntryAsSpamAction::class);
        $this->app->bindIf(RedactFormEntryContract::class, RedactFormEntryAction::class);
        $this->app->bindIf(ShowFormEntryContract::class, ShowFormEntryAction::class);
        $this->app->bindIf(DeleteFormContract::class, DeleteFormAction::class);
        $this->app->bindIf(DuplicateFormContract::class, DuplicateFormAction::class);
        $this->app->bindIf(GetFormAnalyticsBundleContract::class, GetFormAnalyticsBundleAction::class);
        $this->app->bindIf(GetFormAnalyticsSummaryContract::class, GetFormAnalyticsSummaryAction::class);
        $this->app->bindIf(GetFormForRenderContract::class, GetFormForRenderAction::class);
        $this->app->bindIf(GetFormSelectOptionsContract::class, GetFormSelectOptionsAction::class);
        $this->app->bindIf(GetFormSuggestionsContract::class, GetFormSuggestionsAction::class);
        $this->app->bindIf(GetFormValidationSchemaContract::class, GetFormValidationSchemaAction::class);
        $this->app->bindIf(HandlePublicFormSubmissionContract::class, HandlePublicFormSubmissionAction::class);
        $this->app->bindIf(ListFormsContract::class, ListFormsAction::class);
        $this->app->bindIf(SearchFormsContract::class, SearchFormsAction::class);
        $this->app->bindIf(ShowFormContract::class, ShowFormAction::class);
        $this->app->bindIf(UpdateFormContract::class, UpdateFormAction::class);

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
        $this->app->bindIf(CreateFormContract::class, CreateFormAction::class);
        $this->app->bindIf(CreateFormEntryContract::class, CreateFormEntryAction::class);
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
