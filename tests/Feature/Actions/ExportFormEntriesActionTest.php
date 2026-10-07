<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Nvl\Forms\Actions\FormEntry\ExportFormEntriesAction;
use Nvl\Forms\Events\FormChanged;
use Nvl\Forms\Exceptions\FormException;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormEntry;
use Nvl\Forms\Tests\Stubs\TestFormsUser;

beforeEach(function (): void {
    Storage::fake('local');

    Gate::define('authoritative-group', static fn (TestFormsUser $user): bool => true);

    Cache::flush();
});

test('export form entries action writes csv and updates progress tracking', function (): void {
    Event::fake([FormChanged::class]);
    $user = TestFormsUser::factory()->create(['name' => 'Nicolas Vlachos']);
    $form = Form::factory()->create(['handle' => 'contact-form']);
    $foreignProgressKey = 'export_progress_'.$user->getAuthIdentifier().'_'.$form->id.'_host';
    Cache::put($foreignProgressKey, ['status' => 'host-export'], 60);

    FormEntry::factory()->for($form)->create([
        'subject' => '=SUM(1,2)',
        'submission_data' => ['notes' => 'Follow up soon'],
    ]);

    FormEntry::factory()->for($form)->create([
        'subject' => '   =SUM(1,2)',
        'submission_data' => ['notes' => 'Escalate later'],
    ]);

    $path = app(ExportFormEntriesAction::class)->execute($form, [], $user);

    expect($path)->not->toBeEmpty();

    $files = Storage::disk('local')->files('exports/forms');
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

    expect($headers)->toContain('ID', 'Subject', 'Field: notes')
        ->and(array_column($rows, $subjectIndex))->toContain("'=SUM(1,2)", "'   =SUM(1,2)")
        ->and(array_column($rows, $notesIndex))->toContain('Follow up soon', 'Escalate later');

    Event::assertDispatched(
        FormChanged::class,
        static fn (FormChanged $event): bool => $event->operation === 'entries_exported'
            && $event->context['entry_count'] === 2,
    );

    $exportId = explode('_', basename($relativePath), 2)[0];
    $progressKey = 'nvl:forms:export-progress:'.$user->getAuthIdentifier().':'.$form->id.':'.$exportId;
    expect(Cache::get($progressKey))->toBe(['status' => 'completed', 'progress' => 100])
        ->and(Cache::get($foreignProgressKey))->toBe(['status' => 'host-export']);
});

test('export form entries action throws when authentication missing', function (): void {
    $form = Form::factory()->create();

    FormEntry::factory()->for($form)->create();

    $this->expectException(Exception::class);
    $this->expectExceptionMessage(trans('nvl-forms::forms/shared.messages.error.authentication_required'));

    app(ExportFormEntriesAction::class)->execute($form);
});

test('export form entries action rejects empty datasets', function (): void {
    $user = TestFormsUser::factory()->create();
    $form = Form::factory()->create();

    $message = trans('nvl-forms::forms/shared.messages.error.no_export_data', [
        'items' => trans('nvl-forms::entries/general.entities.plural'),
    ]);

    $this->expectException(Exception::class);
    $this->expectExceptionMessage($message);

    app(ExportFormEntriesAction::class)->execute($form, [], $user);
});

test('exports in the same second preserve distinct selected datasets', function (): void {
    $this->freezeTime();
    $user = TestFormsUser::factory()->create();
    $form = Form::factory()->create();
    FormEntry::factory()->for($form)->create(['email' => 'private@example.com']);
    $action = app(ExportFormEntriesAction::class);

    $publicPath = $action->execute($form, ['include_sensitive_data' => false], $user);
    $sensitivePath = $action->execute($form, ['include_sensitive_data' => true], $user);

    expect($publicPath)->not->toBe($sensitivePath)
        ->and(file_get_contents($publicPath))->not->toContain('private@example.com')
        ->and(file_get_contents($sensitivePath))->toContain('private@example.com');
});

test('failed export writes cannot report a completed artifact', function (): void {
    Event::fake([FormChanged::class]);
    $user = TestFormsUser::factory()->create();
    $form = Form::factory()->create();
    FormEntry::factory()->for($form)->create();
    $disk = Mockery::mock(Storage::disk('local'));
    $disk->shouldReceive('put')->andReturnFalse();
    Storage::partialMock()->shouldReceive('disk')->with('local')->andReturn($disk);

    expect(fn () => app(ExportFormEntriesAction::class)->execute($form, [], $user))
        ->toThrow(FormException::class);
    Event::assertNotDispatched(FormChanged::class);
});
