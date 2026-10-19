<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests;

use Closure;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Nvl\Data\Providers\DataServiceProvider;
use Nvl\Filterable\Providers\FilterableServiceProvider;
use Nvl\Forms\Actions\Form\CreateFormAction;
use Nvl\Forms\Data\Mutations\MutateFormPayload;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Providers\FormsServiceProvider;
use Nvl\Forms\Tests\Fixtures\Actor;
use Nvl\Forms\Tests\Fixtures\Company;
use Nvl\Forms\Tests\Fixtures\OwnershipForm;
use Nvl\Forms\Tests\RuntimeOwnershipTestCase as RuntimeTestCase;
use Nvl\Ownership\Contracts\OwnershipAdoptionAccess;
use Nvl\Ownership\Providers\OwnershipServiceProvider;
use Nvl\Ownership\Services\OwnershipAdoptionCoordinator;
use Nvl\Ownership\Services\OwnershipRunner;
use Nvl\Ownership\ValueObjects\OwnershipMapping;
use Nvl\Ownership\ValueObjects\OwnershipOperation;
use Nvl\Support\Providers\LocaleServiceProvider;
use Nvl\Translatable\Providers\TranslatableServiceProvider;

/** Boots standalone Forms with native host ownership and real reviewed activation. */
abstract class OwnershipTestCase extends RuntimeTestCase
{
    private array $originalMorphMap = [];

    protected function tearDown(): void
    {
        parent::tearDown();
        Relation::morphMap($this->originalMorphMap, false);
    }

    protected function getPackageProviders($app): array
    {
        return [LocaleServiceProvider::class, ...parent::getPackageProviders($app), DataServiceProvider::class, FilterableServiceProvider::class, TranslatableServiceProvider::class, FormsServiceProvider::class];
    }

    /** Install an explicitly selected consumer-native pivot before protocol preparation. */
    protected function prepareOwnershipSchema(): void {}

    protected function formClass(): string
    {
        return OwnershipForm::class;
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set([
            'app.key' => 'a-valid-test-only-signing-key-ownership',
            'app.locale' => 'en', 'cache.default' => 'array',
            'nvl-forms.models.form' => $this->formClass(),
            'nvl-forms.migrations.enabled' => false,
            'nvl-forms.routes.public.enabled' => true,
            'nvl-translatable.locales' => ['en', 'bg'],
        ]);
    }

    protected function setUp(): void
    {
        $this->originalMorphMap = Relation::morphMap();
        parent::setUp();
        $this->allowOwners();
        $migrations = array_values(array_filter(glob(__DIR__.'/../database/migrations/*.php') ?: [], static fn (string $path): bool => basename($path) !== '2026_07_29_055330_nvl_forms_create_form_submission_receipts_table.php'));
        $migrations[] = __DIR__.'/../database/migrations/current/2026_07_29_055330_nvl_forms_create_form_submission_receipts_table.php';
        foreach ($migrations as $path) {
            (require $path)->up();
        }
        $class = $this->formClass();
        (new $class)->getConnection()->getSchemaBuilder()->table((new $class)->getTable(), static function (Blueprint $table): void {
            $table->unsignedBigInteger('company_id')->nullable();
            $table->foreign('company_id')->references('id')->on('ownership_test_companies');
        });
        (require dirname((new \ReflectionClass(OwnershipServiceProvider::class))->getFileName(), 3).'/database/migrations/ownership/2026_10_18_000001_nvl_ownership_create_adoption_tables.php')->up();
        $this->app->bind(OwnershipAdoptionAccess::class, static fn () => new class implements OwnershipAdoptionAccess
        {
            public function authorize(OwnershipOperation $operation): void {}
        });
        $this->app->instance(MaintenanceMode::class, new class implements MaintenanceMode
        {
            private bool $enabled = true;

            public function activate(array $payload): void
            {
                $this->enabled = true;
            }

            public function deactivate(): void
            {
                $this->enabled = false;
            }

            public function active(): bool
            {
                return $this->enabled;
            }

            public function data(): array
            {
                return [];
            }
        });
        $this->prepareOwnershipSchema();
        $operation = new OwnershipOperation('adopt-forms', 'operator', '1');
        $coordinator = app(OwnershipAdoptionCoordinator::class);
        $plan = $coordinator->prepare(['forms'], $this->reviewedMappings(), $operation);
        $coordinator->backfill($plan, 100, $operation);
        $coordinator->activate($plan, $operation);
        app(MaintenanceMode::class)->deactivate();
    }

    /** @return list<OwnershipMapping> */
    protected function reviewedMappings(): array
    {
        return [];
    }

    protected function owned(int $id, Closure $callback): mixed
    {
        return app(OwnershipRunner::class)->run(Company::findOrFail($id), Actor::firstOrFail(), $callback);
    }

    protected function createOwnedForm(string $handle = 'contact'): Form
    {
        return app(CreateFormAction::class)->execute(MutateFormPayload::from([
            'handle' => $handle, 'status' => 'active', 'type' => 'iframe', 'resolvement' => 'entries',
            'translations' => ['en' => ['name' => 'Contact']],
            'enableHoneypot' => false, 'enableRateLimiting' => false, 'restrictPublicAccess' => false,
            'requireCsrf' => false,
        ]));
    }
}
