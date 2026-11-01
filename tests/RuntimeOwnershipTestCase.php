<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Nvl\Forms\Tests\Fixtures\Actor;
use Nvl\Forms\Tests\Fixtures\Company;
use Nvl\Ownership\Contracts\OwnerAccess;
use Nvl\Ownership\Contracts\OwnerAvailability;
use Nvl\Ownership\Providers\OwnershipServiceProvider;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;
use Nvl\Support\Providers\SupportServiceProvider;
use Orchestra\Testbench\TestCase;

/** Boots only the neutral Core and optional Ownership runtime against native host keys. */
abstract class RuntimeOwnershipTestCase extends TestCase
{
    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [SupportServiceProvider::class, ...class_exists(OwnershipServiceProvider::class) ? [OwnershipServiceProvider::class] : []];
    }

    /** Configure explicitly owned native models without Tenancy or Auth providers. */
    protected function defineEnvironment($app): void
    {
        $app['config']->set(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.url' => null, 'nvl-core.owners' => [Company::class, Actor::class], 'nvl-ownership.enabled' => true, 'nvl-ownership.migrations.enabled' => false, 'nvl-tenancy.enabled' => false]);
        if (in_array(getenv('NVL_OWNERSHIP_TEST_DRIVER'), ['pgsql', 'mysql', 'mariadb'], true)) {
            $primary = getenv('NVL_OWNERSHIP_TEST_DATABASE');
            $remote = getenv('NVL_OWNERSHIP_TEST_REMOTE_DATABASE');
            foreach ([$primary, $remote] as $database) {
                if (! is_string($database) || preg_match('/^nvl_own_test_[a-z0-9_]+$/D', $database) !== 1) {
                    throw new \RuntimeException('Persistence tests require explicitly dedicated nvl_own_test_ databases.');
                }
            }
            $driver = (string) getenv('NVL_OWNERSHIP_TEST_DRIVER');
            $host = getenv('NVL_OWNERSHIP_TEST_HOST') ?: ($driver === 'pgsql' ? '/tmp' : '127.0.0.1');
            $port = getenv('NVL_OWNERSHIP_TEST_PORT') ?: ($driver === 'pgsql' ? '5432' : '3306');
            if (! in_array($host, ['127.0.0.1', 'localhost', '::1', ...($driver === 'pgsql' ? ['/tmp'] : [])], true) || ! ctype_digit($port)) {
                throw new \RuntimeException('Native ownership fixtures require a loopback service and explicit numeric port.');
            }
            $connection = ['driver' => $driver, 'url' => null, 'host' => $host, 'port' => $port, 'database' => $primary, 'username' => getenv('NVL_OWNERSHIP_TEST_USERNAME') ?: ($driver === 'pgsql' ? get_current_user() : 'root'), 'password' => getenv('NVL_OWNERSHIP_TEST_PASSWORD') ?: '', 'prefix' => '', 'timezone' => $driver === 'pgsql' ? 'UTC' : '+00:00'];
            $connection += $driver === 'pgsql' ? ['charset' => 'utf8', 'search_path' => 'public', 'sslmode' => 'prefer'] : ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_bin', 'strict' => true, 'engine' => 'InnoDB'];
            $app['config']->set('database.connections.sqlite', $connection);
            $app['config']->set('database.connections.ownership_remote', [...$connection, 'database' => $remote]);
        }
    }

    /** Build native application fixtures after provider registration has completed. */
    protected function setUp(): void
    {
        parent::setUp();
        if (in_array(getenv('NVL_OWNERSHIP_TEST_DRIVER'), ['pgsql', 'mysql', 'mariadb'], true)) {
            $this->assertSame(getenv('NVL_OWNERSHIP_TEST_DRIVER'), DB::connection()->getDriverName());
            foreach (['sqlite', 'ownership_remote'] as $connection) {
                DB::connection($connection)->getSchemaBuilder()->dropAllTables();
            }
        }
        Schema::create('ownership_test_companies', static function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->boolean('active')->default(true);
        });
        Schema::create('ownership_test_actors', static function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name');
        });
        Company::query()->create(['id' => 7]);
        Company::query()->create(['id' => 8]);
        Actor::query()->create(['id' => 1, 'name' => 'Operator']);
    }

    /**
     * Bind a host admission policy for the exact native owners.
     *
     * @param  list<int>  $companies
     */
    protected function allowOwners(array $companies = [7, 8]): void
    {
        $this->app->bind(OwnerAccess::class, static fn (): OwnerAccess => new class($companies) implements OwnerAccess
        {
            /** @param list<int> $companies */
            public function __construct(private readonly array $companies) {}

            /** Admit only the fixture actor to explicitly allowed companies. */
            public function assertCanEnter(Authenticatable $actor, Model $owner): void
            {
                if ($actor->getAuthIdentifier() !== 1 || ! in_array($owner->getKey(), $this->companies, true)) {
                    throw new OwnershipViolation;
                }
            }
        });
        $this->app->bind(OwnerAvailability::class, static fn (): OwnerAvailability => new class implements OwnerAvailability
        {
            /** Check freshly persisted host status rather than the supplied model. */
            public function assertAvailable(Model $owner): void
            {
                if ($owner->getAttribute('active') !== true) {
                    throw new OwnershipViolation;
                }
            }
        });
    }
}
