<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Nvl\Forms\Contracts\FormEntryDeletionPolicy;
use Nvl\Forms\Contracts\FormEntryPrivacyPolicy;
use Nvl\Forms\Events\FormEntryChanged;
use Nvl\Forms\Exceptions\FormException;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormAnalytic;
use Nvl\Forms\Models\FormEntry;
use Nvl\Forms\Models\FormRateLimit;
use Nvl\Forms\Services\FormEntries;
use Nvl\Forms\Services\FormRetention;
use Nvl\Forms\Tests\FormsRetentionTestCase;

uses(FormsRetentionTestCase::class);

it('fences the next retention batch after a transaction completion listener changes PDO', function (): void {
    config(['nvl-forms.retention.enabled' => true, 'nvl-forms.retention.authorization_ability' => 'retain-form-data', 'nvl-forms.retention.entries_days' => 30, 'nvl-forms.retention.batch_size' => 1]);
    $form = Form::factory()->create(['submissions_count' => 2]);
    FormEntry::factory()->for($form)->count(2)->create(['created_at' => now()->subDays(60)]);
    $actor = formActor();
    $source = $form->getConnection();
    $pdo = $source->getPdo();
    $replacement = new PDO('sqlite::memory:');
    $table = (new FormEntry)->getTable();
    $replacement->exec('CREATE TABLE "'.$table.'" (id VARCHAR(36) PRIMARY KEY, form_id VARCHAR(36), created_at DATETIME)');
    $foreignQueries = 0;
    $source->beforeExecuting(static function () use ($source, $replacement, &$foreignQueries): void {
        if ($source->getRawPdo() === $replacement) {
            $foreignQueries++;
        }
    });
    $switched = false;
    Event::listen(TransactionCommitted::class, static function (TransactionCommitted $event) use ($source, $replacement, &$switched): void {
        if (! $switched && $event->connection === $source && $source->transactionLevel() === 0) {
            $switched = true;
            $source->setPdo($replacement);
        }
    });
    Event::fake([FormEntryChanged::class]);
    try {
        expect(fn () => app(FormRetention::class)->prune($form, $actor))->toThrow(FormException::class, 'source changed');
        expect($foreignQueries)->toBe(0);
    } finally {
        $source->setPdo($pdo);
    }

    expect(FormEntry::query()->where('form_id', $form->id)->count())->toBe(1)
        ->and($form->fresh()->submissions_count)->toBe(1);
    Event::assertDispatchedTimes(FormEntryChanged::class, 1);
});

it('rejects an entry connection alias changed by its retention deleting listener', function (): void {
    config(['nvl-forms.retention.enabled' => true, 'nvl-forms.retention.authorization_ability' => 'retain-form-data', 'nvl-forms.retention.entries_days' => 30]);
    $entry = FormEntry::factory()->create(['created_at' => now()->subDays(60)]);
    $form = $entry->form;
    $actor = formActor();
    config(['database.connections.forms_retention_foreign' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    $foreign = DB::connection('forms_retention_foreign');
    $foreign->getPdo()->exec('CREATE TABLE "'.$entry->getTable().'" (id VARCHAR(36) PRIMARY KEY, created_at DATETIME NOT NULL)');
    $foreign->table($entry->getTable())->insert(['id' => $entry->id, 'created_at' => now()->subDays(60)->format('Y-m-d H:i:s')]);
    $foreignQueries = 0;
    $foreign->beforeExecuting(static function () use (&$foreignQueries): void {
        $foreignQueries++;
        throw new RuntimeException('A retention statement reached the foreign entry alias.');
    });
    FormEntry::deleting(static function (FormEntry $deleting): void {
        $deleting->setConnection('forms_retention_foreign');
    });
    Event::fake([FormEntryChanged::class]);

    try {
        expect(fn () => app(FormRetention::class)->prune($form, $actor))->toThrow(FormException::class, 'source changed');
        expect($foreignQueries)->toBe(0)->and(FormEntry::query()->whereKey($entry->id)->exists())->toBeTrue();
        Event::assertNotDispatched(FormEntryChanged::class);
    } finally {
        DB::purge('forms_retention_foreign');
    }
});

beforeEach(function (): void {
    $driver = getenv('NVL_C1_TEST_DRIVER');
    if (in_array($driver, ['pgsql', 'mysql', 'mariadb'], true)) {
        $source = (new Form)->getConnection();
        expect($source->getDriverName())->toBe($driver)
            ->and($source->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME))->toBe($driver === 'mariadb' ? 'mysql' : $driver);
    }
    Storage::fake('local');
    Gate::define('retain-form-data', static fn (): bool => true);
});

it('requires explicit retention enablement ages and authority before deleting anything', function (string $missing): void {
    config(['nvl-forms.retention.enabled' => $missing !== 'enabled', 'nvl-forms.retention.authorization_ability' => $missing === 'gate' ? null : 'retain-form-data', 'nvl-forms.retention.entries_days' => $missing === 'age' ? null : 30]);
    $entry = FormEntry::factory()->create(['created_at' => now()->subDays(60)]);

    expect(fn () => app(FormRetention::class)->prune($entry->form, formActor()))->toThrow($missing === 'age' ? FormException::class : AuthorizationException::class)
        ->and(FormEntry::query()->whereKey($entry->id)->exists())->toBeTrue();
})->with(['enabled', 'gate', 'age']);

it('dry-runs and deletes only a bounded selected Form inventory', function (): void {
    config(['nvl-forms.retention.enabled' => true, 'nvl-forms.retention.authorization_ability' => 'retain-form-data', 'nvl-forms.retention.entries_days' => 30, 'nvl-forms.retention.maximum_records' => 2, 'nvl-forms.retention.batch_size' => 1]);
    $form = Form::factory()->create(['submissions_count' => 3]);
    FormEntry::factory()->for($form)->count(3)->create(['is_spam' => false, 'created_at' => now()->subDays(60)]);
    $recent = FormEntry::factory()->for($form)->create(['created_at' => now()]);
    $foreign = FormEntry::factory()->create(['created_at' => now()->subDays(60)]);
    $retention = app(FormRetention::class);

    expect($retention->prune($form, formActor(), true))->toBe(['entries' => 2, 'analytics' => 0, 'rates' => 0, 'exports' => 0, 'has_more' => true])
        ->and($form->entries()->count())->toBe(4);
    expect($retention->prune($form, formActor()))->toBe(['entries' => 2, 'analytics' => 0, 'rates' => 0, 'exports' => 0, 'has_more' => true])
        ->and($form->entries()->count())->toBe(2)
        ->and($form->fresh()->submissions_count)->toBe(1)
        ->and(FormEntry::query()->whereKey($recent->id)->exists())->toBeTrue()
        ->and(FormEntry::query()->whereKey($foreign->id)->exists())->toBeTrue();
});

it('retains existing entry legal holds and stops on current policy denial', function (): void {
    config(['nvl-forms.retention.enabled' => true, 'nvl-forms.retention.authorization_ability' => 'retain-form-data', 'nvl-forms.retention.entries_days' => 30]);
    $entry = FormEntry::factory()->create(['created_at' => now()->subDays(60)]);
    app()->instance(FormEntryDeletionPolicy::class, new class implements FormEntryDeletionPolicy
    {
        public function authorize(FormEntry $entry, ?Authenticatable $actor = null): void
        {
            throw new AuthorizationException('Entry is under legal hold.');
        }
    });

    expect(fn () => app(FormRetention::class)->prune($entry->form, formActor()))->toThrow(AuthorizationException::class, 'legal hold')
        ->and(FormEntry::query()->whereKey($entry->id)->exists())->toBeTrue();
});

it('retains private entries when the host rejects the retention purpose', function (bool $dryRun): void {
    config(['nvl-forms.retention.enabled' => true, 'nvl-forms.retention.authorization_ability' => 'retain-form-data', 'nvl-forms.retention.entries_days' => 30]);
    $entry = FormEntry::factory()->create(['created_at' => now()->subDays(60)]);
    app()->instance(FormEntryPrivacyPolicy::class, new class implements FormEntryPrivacyPolicy
    {
        public function authorize(string $operation, FormEntry $entry, ?Authenticatable $actor = null): void
        {
            expect($operation)->toBe('retention');
            throw new AuthorizationException('Entry privacy prohibits retention deletion.');
        }
    });

    expect(fn () => app(FormRetention::class)->prune($entry->form, formActor(), $dryRun))->toThrow(AuthorizationException::class, 'privacy')
        ->and(FormEntry::query()->whereKey($entry->id)->exists())->toBeTrue();
})->with([false, true]);

it('rejects a replaced source PDO during retention authorization before deleting a record', function (): void {
    config(['nvl-forms.retention.enabled' => true, 'nvl-forms.retention.authorization_ability' => 'retain-form-data', 'nvl-forms.retention.entries_days' => 30]);
    $entry = FormEntry::factory()->create(['created_at' => now()->subDays(60)]);
    $actor = formActor();
    $source = $entry->getConnection();
    $pdo = $source->getPdo();
    Gate::define('retain-form-data', static function () use ($source): bool {
        $source->setPdo(new PDO('sqlite::memory:'));

        return true;
    });
    try {
        expect(fn () => app(FormRetention::class)->prune($entry->form, $actor))->toThrow(FormException::class, 'source changed');
    } finally {
        $source->setPdo($pdo);
    }

    expect(FormEntry::query()->whereKey($entry->id)->exists())->toBeTrue();
});

it('rechecks current retention authority after entry policy hooks before deleting', function (): void {
    config(['nvl-forms.retention.enabled' => true, 'nvl-forms.retention.authorization_ability' => 'retain-form-data', 'nvl-forms.retention.entries_days' => 30]);
    $entry = FormEntry::factory()->create(['created_at' => now()->subDays(60)]);
    app()->instance(FormEntryPrivacyPolicy::class, new class implements FormEntryPrivacyPolicy
    {
        public function authorize(string $operation, FormEntry $entry, ?Authenticatable $actor = null): void
        {
            config(['nvl-forms.retention.enabled' => false]);
        }
    });

    expect(fn () => app(FormRetention::class)->prune($entry->form, formActor()))->toThrow(AuthorizationException::class, 'disabled')
        ->and(FormEntry::query()->whereKey($entry->id)->exists())->toBeTrue();
});

it('rechecks the fixed age predicate after a selected entry is renewed', function (): void {
    config(['nvl-forms.retention.enabled' => true, 'nvl-forms.retention.authorization_ability' => 'retain-form-data', 'nvl-forms.retention.entries_days' => 30]);
    $entry = FormEntry::factory()->create(['created_at' => now()->subDays(60)]);
    $form = $entry->form;
    $actor = formActor();
    $renewed = false;
    FormEntry::retrieved(static function (FormEntry $selected) use ($entry, &$renewed): void {
        if (! $renewed && $selected->id === $entry->id) {
            $renewed = true;
            $selected->getConnection()->table($selected->getTable())->where('id', $selected->id)->update(['created_at' => now()]);
        }
    });

    expect(app(FormRetention::class)->prune($form, $actor)['entries'])->toBe(0)
        ->and($renewed)->toBeTrue()->and(FormEntry::query()->whereKey($entry->id)->exists())->toBeTrue();
});

it('preserves the cutoff at the actual entry delete after the final policy or model hook', function (string $hook): void {
    config(['nvl-forms.retention.enabled' => true, 'nvl-forms.retention.authorization_ability' => 'retain-form-data', 'nvl-forms.retention.entries_days' => 30]);
    $entry = FormEntry::factory()->create(['created_at' => now()->subDays(60)]);
    $form = $entry->form;
    $actor = formActor();
    Event::fake([FormEntryChanged::class]);
    if ($hook === 'policy') {
        app()->instance(FormEntryDeletionPolicy::class, new class implements FormEntryDeletionPolicy
        {
            private int $calls = 0;

            public function authorize(FormEntry $entry, ?Authenticatable $actor = null): void
            {
                if (++$this->calls === 2) {
                    $entry->getConnection()->table($entry->getTable())->where('id', $entry->id)->update(['created_at' => now()]);
                }
            }
        });
    } else {
        FormEntry::deleting(static function (FormEntry $deleting): void {
            $deleting->getConnection()->table($deleting->getTable())->where('id', $deleting->id)->update(['created_at' => now()]);
        });
    }

    expect(app(FormRetention::class)->prune($form, $actor)['entries'])->toBe(0)
        ->and(FormEntry::query()->whereKey($entry->id)->exists())->toBeTrue()
        ->and(FormEntry::query()->findOrFail($entry->id)->created_at->isToday())->toBeTrue();
    Event::assertNotDispatched(FormEntryChanged::class);
})->with(['policy', 'model']);

it('fences a deleting-listener PDO replacement without modifying either source', function (bool $throws): void {
    config(['nvl-forms.retention.enabled' => true, 'nvl-forms.retention.authorization_ability' => 'retain-form-data', 'nvl-forms.retention.entries_days' => 30]);
    $entry = FormEntry::factory()->create(['body' => 'Before retention', 'created_at' => now()->subDays(60)]);
    $form = $entry->form;
    $actor = formActor();
    $source = $entry->getConnection();
    $pdo = $source->getPdo();
    $replacement = new PDO('sqlite::memory:');
    $table = $entry->getTable();
    $replacement->exec('CREATE TABLE "'.$table.'" (id VARCHAR(36) PRIMARY KEY, created_at DATETIME NOT NULL)');
    $insert = $replacement->prepare('INSERT INTO "'.$table.'" (id, created_at) VALUES (?, ?)');
    $insert->execute([$entry->id, now()->subDays(60)->format('Y-m-d H:i:s')]);
    $replacement->beginTransaction();
    $committed = 0;
    FormEntry::deleting(static function (FormEntry $deleting) use ($source, $replacement, $throws, &$committed): void {
        $source->table($deleting->getTable())->where('id', $deleting->id)->update(['body' => 'This owned write must roll back']);
        $source->afterCommit(static function () use (&$committed): void {
            $committed++;
        });
        $source->setPdo($replacement);
        if ($throws) {
            throw new RuntimeException('Listener failed after replacing its writer.');
        }
    });

    try {
        expect(fn () => app(FormRetention::class)->prune($form, $actor))->toThrow(FormException::class, 'source changed');
        expect($pdo->inTransaction())->toBeFalse()->and($source->transactionLevel())->toBe(0)
            ->and($source->getRawPdo())->toBe($pdo)->and($committed)->toBe(0);
        expect(FormEntry::query()->findOrFail($entry->id)->body)->toBe('Before retention');
        $source->transaction(static fn (): null => null);
        expect($committed)->toBe(0);
    } finally {
        $source->setPdo($pdo);
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
    expect(FormEntry::query()->whereKey($entry->id)->exists())->toBeTrue()
        ->and($replacement->query('SELECT COUNT(*) FROM "'.$table.'"')->fetchColumn())->toBe(1)
        ->and($replacement->inTransaction())->toBeTrue();
    $replacement->rollBack();
})->with([false, true]);

it('rejects destructive retention within a host outer transaction', function (): void {
    config(['nvl-forms.retention.enabled' => true, 'nvl-forms.retention.authorization_ability' => 'retain-form-data', 'nvl-forms.retention.entries_days' => 30]);
    $entry = FormEntry::factory()->create(['created_at' => now()->subDays(60)]);
    $actor = formActor();

    expect(fn () => $entry->getConnection()->transaction(fn () => app(FormRetention::class)->prune($entry->form, $actor)))->toThrow(FormException::class, 'outside host transactions')
        ->and(FormEntry::query()->whereKey($entry->id)->exists())->toBeTrue();
});

it('rejects transactional dry runs while preserving the host transaction', function (): void {
    config(['nvl-forms.retention.enabled' => true, 'nvl-forms.retention.authorization_ability' => 'retain-form-data', 'nvl-forms.retention.entries_days' => 30]);
    $entry = FormEntry::factory()->create(['created_at' => now()->subDays(60)]);
    $actor = formActor();

    $source = $entry->getConnection();
    $source->beginTransaction();
    try {
        expect(fn () => app(FormRetention::class)->prune($entry->form, $actor, true))->toThrow(FormException::class, 'outside host transactions')
            ->and($source->transactionLevel())->toBe(1)->and($source->getPdo()->inTransaction())->toBeTrue()
            ->and(FormEntry::query()->whereKey($entry->id)->exists())->toBeTrue();
    } finally {
        $source->rollBack();
    }
});

it('rejects preexisting native PDO transactions without rolling back host work', function (): void {
    config(['nvl-forms.retention.enabled' => true, 'nvl-forms.retention.authorization_ability' => 'retain-form-data', 'nvl-forms.retention.entries_days' => 30]);
    $entry = FormEntry::factory()->create(['created_at' => now()->subDays(60)]);
    $actor = formActor();
    $source = $entry->getConnection();
    $pdo = $source->getPdo();
    $pdo->beginTransaction();
    try {
        expect(fn () => app(FormRetention::class)->prune($entry->form, $actor))->toThrow(FormException::class, 'outside host transactions')
            ->and($pdo->inTransaction())->toBeTrue()->and($source->transactionLevel())->toBe(0);
    } finally {
        $pdo->rollBack();
    }
    expect(FormEntry::query()->whereKey($entry->id)->exists())->toBeTrue();
});

it('commits only completed records before a later policy denial without a false deletion event', function (): void {
    config(['nvl-forms.retention.enabled' => true, 'nvl-forms.retention.authorization_ability' => 'retain-form-data', 'nvl-forms.retention.entries_days' => 30, 'nvl-forms.retention.batch_size' => 1]);
    $form = Form::factory()->create(['submissions_count' => 2]);
    FormEntry::factory()->for($form)->count(2)->create(['is_spam' => false, 'created_at' => now()->subDays(60)]);
    $actor = formActor();
    Event::fake([FormEntryChanged::class]);
    app()->instance(FormEntryDeletionPolicy::class, new class implements FormEntryDeletionPolicy
    {
        private int $calls = 0;

        public function authorize(FormEntry $entry, ?Authenticatable $actor = null): void
        {
            if (++$this->calls > 2) {
                throw new AuthorizationException('Later entry is under legal hold.');
            }
        }
    });

    expect(fn () => app(FormRetention::class)->prune($form, $actor))->toThrow(AuthorizationException::class, 'legal hold')
        ->and($form->entries()->count())->toBe(1)->and($form->fresh()->submissions_count)->toBe(1);
    Event::assertDispatchedTimes(FormEntryChanged::class, 1);
});

it('prunes only explicitly aged telemetry while preserving live rate blocks', function (): void {
    config(['nvl-forms.retention.enabled' => true, 'nvl-forms.retention.authorization_ability' => 'retain-form-data', 'nvl-forms.retention.analytics_days' => 30, 'nvl-forms.retention.rates_days' => 30]);
    $form = Form::factory()->create();
    FormAnalytic::factory()->for($form)->create(['created_at' => now()->subDays(60)]);
    $foreign = FormAnalytic::factory()->create(['created_at' => now()->subDays(60)]);
    FormRateLimit::factory()->for($form)->create(['window_start' => now()->subDays(60), 'is_blocked' => false]);
    $blocked = FormRateLimit::factory()->for($form)->create(['window_start' => now()->subDays(60), 'is_blocked' => true, 'blocked_until' => now()->addDay()]);

    expect(app(FormRetention::class)->prune($form, formActor()))->toBe(['entries' => 0, 'analytics' => 1, 'rates' => 1, 'exports' => 0, 'has_more' => false])
        ->and(FormAnalytic::query()->whereKey($foreign->id)->exists())->toBeTrue()
        ->and(FormRateLimit::query()->whereKey($blocked->id)->exists())->toBeTrue();
});

it('prunes only old verified files for the selected Form and leaves legacy flat exports alone', function (): void {
    config(['nvl-forms.retention.enabled' => true, 'nvl-forms.retention.authorization_ability' => 'retain-form-data', 'nvl-forms.retention.exports_hours' => 1]);
    $actor = formActor();
    $entry = FormEntry::factory()->create();
    $foreign = FormEntry::factory()->create();
    $path = app(FormEntries::class)->export($entry->form, [], $actor);
    $foreignPath = app(FormEntries::class)->export($foreign->form, [], $actor);
    touch($path, now()->subHours(2)->timestamp);
    touch($foreignPath, now()->subHours(2)->timestamp);
    Storage::disk('local')->put('exports/forms/legacy.csv', 'Private historical export');
    touch(Storage::disk('local')->path('exports/forms/legacy.csv'), now()->subHours(2)->timestamp);

    expect(app(FormRetention::class)->prune($entry->form, $actor, true)['exports'])->toBe(1)->and(file_exists($path))->toBeTrue();
    expect(app(FormRetention::class)->prune($entry->form, $actor)['exports'])->toBe(1)
        ->and(file_exists($path))->toBeFalse()->and(file_exists($foreignPath))->toBeTrue()
        ->and(Storage::disk('local')->exists('exports/forms/legacy.csv'))->toBeTrue();
});

it('rechecks a verified export cutoff after current authorization before file deletion', function (): void {
    config(['nvl-forms.retention.enabled' => true, 'nvl-forms.retention.authorization_ability' => 'retain-form-data', 'nvl-forms.retention.exports_hours' => 1]);
    $actor = formActor();
    $entry = FormEntry::factory()->create();
    $path = app(FormEntries::class)->export($entry->form, [], $actor);
    touch($path, now()->subHours(2)->timestamp);
    $calls = 0;
    Gate::define('retain-form-data', static function () use ($path, &$calls): bool {
        if (++$calls === 2) {
            touch($path, now()->timestamp);
            clearstatcache(true, $path);
        }

        return true;
    });

    expect(app(FormRetention::class)->prune($entry->form, $actor)['exports'])->toBe(0)
        ->and(file_exists($path))->toBeTrue();
});

it('keeps equal Form identities on different configured sources in separate retention directories', function (): void {
    config(['nvl-forms.retention.enabled' => true, 'nvl-forms.retention.authorization_ability' => 'retain-form-data', 'nvl-forms.retention.exports_hours' => 1]);
    $actor = formActor();
    $entry = FormEntry::factory()->create();
    $formId = $entry->form_id;
    $firstPath = app(FormEntries::class)->export($entry->form, [], $actor);
    touch($firstPath, now()->subHours(2)->timestamp);
    config(['database.connections.retention_second' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true], 'nvl-forms.connection' => 'retention_second']);
    $migrations = array_values(array_filter(glob(__DIR__.'/../database/migrations/*.php') ?: [], static fn (string $path): bool => basename($path) !== '2026_07_29_055330_nvl_forms_create_form_submission_receipts_table.php'));
    $migrations[] = __DIR__.'/../database/migrations/current/2026_07_29_055330_nvl_forms_create_form_submission_receipts_table.php';
    foreach ($migrations as $path) {
        (require $path)->up();
    }
    $secondForm = Form::factory()->create(['id' => $formId]);
    FormEntry::factory()->for($secondForm)->create();
    $secondPath = app(FormEntries::class)->export($secondForm, [], $actor);
    touch($secondPath, now()->subHours(2)->timestamp);

    expect(app(FormRetention::class)->prune($secondForm, $actor)['exports'])->toBe(1)
        ->and(file_exists($firstPath))->toBeTrue()->and(file_exists($secondPath))->toBeFalse();
});
