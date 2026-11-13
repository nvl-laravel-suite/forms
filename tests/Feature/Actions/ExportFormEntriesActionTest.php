<?php

declare(strict_types=1);
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Nvl\Forms\Events\FormChanged;
use Nvl\Forms\Exceptions\FormException;
use Nvl\Forms\Exceptions\FormOwnershipException;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormEntry;
use Nvl\Forms\Services\FormEntries;
use Nvl\Forms\Tests\Stubs\TestFormsUser;

beforeEach(function (): void {
    $driver = getenv('NVL_C1_TEST_DRIVER');
    if ($driver === 'pgsql') {
        $source = (new Form)->getConnection();
        expect($source->getDriverName())->toBe($driver)
            ->and($source->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME))->toBe($driver);
    }
    Storage::fake('local');
    Gate::define('authoritative-group', static fn (TestFormsUser $user): bool => true);
    Cache::flush();
});

it('fences final management authorization before rereading a replacement export source', function (): void {
    $user = formActor();
    $entry = FormEntry::factory()->create();
    $form = $entry->form;
    $source = $form->getConnection();
    $pdo = $source->getPdo();
    $replacement = new PDO('sqlite::memory:');
    $calls = 0;
    $foreignQueries = 0;
    $policy = new class
    {
        public function export(TestFormsUser $actor, Form $form): bool
        {
            return true;
        }
    };
    app()->instance($policy::class, $policy);
    Gate::policy(Form::class, $policy::class);
    Gate::define('manage-forms', static function () use ($source, $replacement, &$calls): bool {
        if (++$calls === 2) {
            $source->setPdo($replacement);
        }

        return true;
    });
    $source->beforeExecuting(static function (string $query, array $bindings, Connection $connection) use ($replacement, &$foreignQueries): void {
        if ($connection->getRawPdo() === $replacement) {
            $foreignQueries++;
        }
    });
    try {
        expect(fn () => app(FormEntries::class)->export($form, [], $user))->toThrow(FormOwnershipException::class, 'source changed')
            ->and($foreignQueries)->toBe(0)->and(Storage::disk('local')->allFiles('exports/forms'))->toBe([]);
    } finally {
        $source->setPdo($pdo);
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
});
test('export form entries action writes csv and updates progress tracking', function (): void {
    Event::fake([FormChanged::class]);
    $user = TestFormsUser::factory()->create(['name' => 'Nicolas Vlachos']);
    $form = Form::factory()->create(['handle' => 'contact-form']);
    $foreignProgressKey = 'export_progress_'.$user->getAuthIdentifier().'_'.$form->id.'_host';
    Cache::put($foreignProgressKey, ['status' => 'host-export'], 60);
    FormEntry::factory()->for($form)->create(['subject' => '=SUM(1,2)', 'submission_data' => ['notes' => 'Follow up soon']]);
    FormEntry::factory()->for($form)->create(['subject' => '   =SUM(1,2)', 'submission_data' => ['notes' => 'Escalate later']]);
    $path = app(FormEntries::class)->export($form, [], $user);
    expect($path)->not->toBeEmpty();
    $files = Storage::disk('local')->allFiles('exports/forms');
    expect($files)->toHaveCount(1);
    $relativePath = $files[0];
    expect($path)->toBe(Storage::disk('local')->path($relativePath));
    $csv = Storage::disk('local')->get($relativePath);
    $csvLines = array_filter(explode("\n", trim($csv)));
    $headers = str_getcsv($csvLines[0]);
    $rows = array_map(static fn (string $line): array => str_getcsv($line), array_slice($csvLines, 1));
    $subjectIndex = array_search('Subject', $headers, true);
    $notesIndex = array_search('Field: notes', $headers, true);
    if ($subjectIndex === false || $notesIndex === false) {
        $this->fail('Expected the export CSV to include Subject and Field: notes columns.');
    }
    expect($headers)->toContain('ID', 'Subject', 'Field: notes')->and(array_column($rows, $subjectIndex))->toContain("'=SUM(1,2)", "'   =SUM(1,2)")->and(array_column($rows, $notesIndex))->toContain('Follow up soon', 'Escalate later');
    Event::assertDispatched(FormChanged::class, static fn (FormChanged $event): bool => $event->operation === 'entries_exported' && $event->context['entry_count'] === 2);
    $exportId = explode('_', basename($relativePath), 2)[0];
    $progressKey = 'nvl:forms:export-progress:'.$user->getAuthIdentifier().':'.$form->id.':'.$exportId;
    expect(Cache::get($progressKey))->toBe(['status' => 'completed', 'progress' => 100])->and(Cache::get($foreignProgressKey))->toBe(['status' => 'host-export']);
});
test('export form entries action throws when authentication missing', function (): void {
    $form = Form::factory()->create();
    FormEntry::factory()->for($form)->create();
    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Forms management requires an authorized actor.');
    app(FormEntries::class)->export($form);
});
test('export form entries action rejects empty datasets', function (): void {
    $user = TestFormsUser::factory()->create();
    $form = Form::factory()->create();
    $message = trans('nvl-forms::forms/shared.messages.error.no_export_data', ['items' => trans('nvl-forms::entries/general.entities.plural')]);
    $this->expectException(Exception::class);
    $this->expectExceptionMessage($message);
    app(FormEntries::class)->export($form, [], $user);
});
test('exports in the same second preserve distinct selected datasets', function (): void {
    $this->freezeTime();
    $user = TestFormsUser::factory()->create();
    $form = Form::factory()->create();
    FormEntry::factory()->for($form)->create(['email' => 'private@example.com']);
    $action = app(FormEntries::class);
    $publicPath = $action->export($form, ['include_sensitive_data' => false], $user);
    $sensitivePath = $action->export($form, ['include_sensitive_data' => true], $user);
    expect($publicPath)->not->toBe($sensitivePath)->and(file_get_contents($publicPath))->not->toContain('private@example.com')->and(file_get_contents($sensitivePath))->toContain('private@example.com');
});
test('failed export writes cannot report a completed artifact', function (): void {
    Event::fake([FormChanged::class]);
    $user = TestFormsUser::factory()->create();
    $form = Form::factory()->create();
    FormEntry::factory()->for($form)->create();
    $disk = Mockery::mock(Storage::disk('local'));
    $disk->shouldReceive('put')->andReturnFalse();
    Storage::partialMock()->shouldReceive('disk')->with('local')->andReturn($disk);
    expect(fn () => app(FormEntries::class)->export($form, [], $user))->toThrow(FormException::class);
    Event::assertNotDispatched(FormChanged::class);
});

test('rejects an export exceeding its configured row budget without publishing an artifact', function (): void {
    Event::fake([FormChanged::class]);
    config(['nvl-forms.exports.maximum_rows' => 2]);
    $form = Form::factory()->create();
    FormEntry::factory()->for($form)->count(3)->create();

    expect(fn () => app(FormEntries::class)->export($form, [], formActor()))->toThrow(FormException::class)
        ->and(Storage::disk('local')->allFiles('exports/forms'))->toBe([]);
    Event::assertNotDispatched(FormChanged::class);
});

test('rejects dynamic-column and encoded-byte overflow without retaining private exports', function (string $budget): void {
    config(['nvl-forms.exports.'.$budget => $budget === 'maximum_dynamic_fields' ? 1 : 128]);
    $form = Form::factory()->create();
    FormEntry::factory()->for($form)->create(['body' => str_repeat('Private body ', 80), 'submission_data' => ['first' => 'one', 'second' => 'two']]);

    expect(fn () => app(FormEntries::class)->export($form, [], formActor()))->toThrow(FormException::class)
        ->and(Storage::disk('local')->allFiles('exports/forms'))->toBe([]);
})->with(['maximum_dynamic_fields', 'maximum_bytes']);

test('refuses a remote export disk instead of returning a nonlocal path', function (): void {
    config(['filesystems.disks.forms-remote' => ['driver' => 's3'], 'nvl-forms.exports.disk' => 'forms-remote']);
    $form = Form::factory()->create();
    FormEntry::factory()->for($form)->create();

    expect(fn () => app(FormEntries::class)->export($form, [], formActor()))->toThrow(FormException::class)
        ->and(Storage::disk('local')->allFiles('exports/forms'))->toBe([]);
});

test('rechecks current export permission before publishing the private snapshot', function (): void {
    $policy = new class
    {
        public int $checks = 0;

        public function export(TestFormsUser $actor, Form $form): bool
        {
            return ++$this->checks === 1;
        }
    };
    app()->instance($policy::class, $policy);
    Gate::policy(Form::class, $policy::class);
    $form = Form::factory()->create();
    FormEntry::factory()->for($form)->create();

    expect(fn () => app(FormEntries::class)->export($form, [], formActor()))->toThrow(AuthorizationException::class)
        ->and(Storage::disk('local')->allFiles('exports/forms'))->toBe([]);
});

test('exports every selected row through bounded fetches with stable same-time ordering', function (): void {
    config(['nvl-forms.exports.chunk_size' => 2]);
    $form = Form::factory()->create();
    $created = now()->startOfSecond();
    $entries = FormEntry::factory()->for($form)->count(5)->create(['created_at' => $created, 'body' => 'Row data', 'submission_data' => ['same' => 'column']]);
    $queries = [];
    $table = (new FormEntry)->getConnection()->getQueryGrammar()->wrapTable((new FormEntry)->getTable());
    DB::listen(static function (QueryExecuted $query) use (&$queries, $table): void {
        if (str_starts_with(strtolower($query->sql), 'select ') && str_contains($query->sql, 'from '.$table)) {
            $queries[] = strtolower($query->sql);
        }
    });
    $path = app(FormEntries::class)->export($form, ['date_from' => '2020-01-01', 'date_to' => '2030-12-31'], formActor());
    $stream = fopen($path, 'r');
    $rows = [];
    try {
        fgetcsv($stream, escape: '');
        while (($row = fgetcsv($stream, escape: '')) !== false) {
            $rows[] = $row[0];
        }
    } finally {
        fclose($stream);
    }
    expect($rows)->toBe($entries->pluck('id')->sortDesc()->values()->all())
        ->and($queries)->not->toBeEmpty();
    foreach ($queries as $query) {
        expect($query)->toContain('limit 2')->not->toContain('date(')->not->toContain('strftime(')->not->toContain('::date');
    }
});

test('writes captured dynamic cells when persistence changes after a batch is read', function (): void {
    $form = Form::factory()->create();
    $entry = FormEntry::factory()->for($form)->create(['body' => 'Captured body', 'submission_data' => ['before' => 'Captured value']]);
    $changed = false;
    FormEntry::retrieved(static function (FormEntry $loaded) use (&$changed, $entry): void {
        if ($changed || $loaded->id !== $entry->id) {
            return;
        }
        $changed = true;
        $loaded->getConnection()->table($loaded->getTable())->where('id', $loaded->id)->update(['body' => 'Changed body', 'submission_data' => json_encode(['after' => 'Changed value'], JSON_THROW_ON_ERROR)]);
    });

    $path = app(FormEntries::class)->export($form, [], formActor());

    expect($changed)->toBeTrue()->and(file_get_contents($path))->toContain('Field: before', 'Captured value', 'Captured body')->not->toContain('Field: after', 'Changed value', 'Changed body');
});

it('preserves inclusive calendar date boundaries without wrapping the timestamp column', function (): void {
    $form = Form::factory()->create();
    $first = FormEntry::factory()->for($form)->create(['created_at' => '2026-10-31 00:00:00']);
    $last = FormEntry::factory()->for($form)->create(['created_at' => '2026-10-31 23:59:59']);
    $before = FormEntry::factory()->for($form)->create(['created_at' => '2026-10-30 23:59:59']);
    $after = FormEntry::factory()->for($form)->create(['created_at' => '2026-11-01 00:00:00']);
    $csv = file_get_contents(app(FormEntries::class)->export($form, ['date_from' => '2026-10-31', 'date_to' => '2026-10-31'], formActor()));

    expect($csv)->toContain($first->id, $last->id)->not->toContain($before->id, $after->id);
});

it('exports valid entries with nullable creation timestamps across bounded chunks', function (): void {
    config(['nvl-forms.exports.chunk_size' => 2]);
    $form = Form::factory()->create();
    $entries = FormEntry::factory()->for($form)->count(5)->create();
    foreach ($entries->take(3) as $entry) {
        $entry->getConnection()->table($entry->getTable())->where('id', $entry->id)->update(['created_at' => null]);
    }
    $stream = fopen(app(FormEntries::class)->export($form, [], formActor()), 'r');
    $identifiers = [];
    try {
        fgetcsv($stream, escape: '');
        while (($row = fgetcsv($stream, escape: '')) !== false) {
            $identifiers[] = $row[0];
        }
    } finally {
        fclose($stream);
    }

    expect($identifiers)->toHaveCount(5)
        ->and(collect($identifiers)->sort()->values()->all())->toBe($entries->pluck('id')->sort()->values()->all());
});
