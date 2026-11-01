<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Exercises real per-record commits without a fixture-owned outer transaction. */
abstract class FormsRetentionTestCase extends FormsTestCase
{
    protected function setUp(): void
    {
        RefreshDatabaseState::$migrated = false;
        parent::setUp();
    }

    /** Refuse migration cleanup on application or shared databases. */
    protected function migrateDatabases(): void
    {
        $source = DB::connection();
        $driver = $source->getDriverName();
        $database = $source->getDatabaseName();
        $safe = $driver === 'sqlite' && $database === ':memory:';
        if (in_array($driver, ['pgsql', 'mysql', 'mariadb'], true)) {
            $safe = preg_match('/\Anvl_forms_test_[a-f0-9]{8}_ci\z/D', $database) === 1
                && in_array($source->getConfig('host'), ['127.0.0.1', 'localhost', '::1', ...($driver === 'pgsql' ? ['/tmp'] : [])], true);
        }
        if (! $safe) {
            throw new RuntimeException('Committed Forms retention tests require memory SQLite or an explicitly dedicated loopback nvl_forms_test_<8hex>_ci database.');
        }
        parent::migrateDatabases();
    }

    /** Replace fixture transactions with guarded disposable-schema cleanup. */
    public function beginDatabaseTransaction(): void
    {
        $source = DB::connection();
        $pdo = $source->getPdo();
        $database = $source->getDatabaseName();
        $this->beforeApplicationDestroyed(function () use ($source, $pdo, $database): void {
            RefreshDatabaseState::$migrated = false;
            foreach ($this->connectionsToTransact() as $name) {
                $key = $name ?? '';
                if (! is_string($key)) {
                    throw new RuntimeException('Disposable Forms connections require native string names.');
                }
                unset(RefreshDatabaseState::$inMemoryConnections[$key]);
            }
            if ($source->getRawPdo() !== $pdo || $source->getDatabaseName() !== $database) {
                throw new RuntimeException('The disposable Forms retention source changed before cleanup.');
            }
            while ($source->transactionLevel() > 0) {
                $source->rollBack();
            }
            $source->getSchemaBuilder()->dropAllTables();
        });
    }
}
