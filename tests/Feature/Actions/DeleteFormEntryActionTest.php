<?php

declare(strict_types=1);
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Event;
use Nvl\Forms\Contracts\FormEntryDeletionPolicy;
use Nvl\Forms\Definitions\Tables\FormsTables;
use Nvl\Forms\Events\FormEntryChanged;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormEntry;
use Nvl\Forms\Services\FormEntries;

test('delete form entry action removes entry and updates counters', function (): void {
    Event::fake([FormEntryChanged::class]);
    $form = Form::factory()->create(['submissions_count' => 1]);
    $entry = FormEntry::factory()->for($form)->create(['is_spam' => false, 'created_at' => now()]);
    $deleted = app(FormEntries::class)->delete($entry, actor: formActor());
    expect($deleted)->toBeTrue();
    $this->assertDatabaseMissing(FormsTables::Entries, ['id' => $entry->id]);
    Event::assertDispatched(FormEntryChanged::class);
    $form->refresh();
    expect($form->submissions_count)->toBe(0);
});
test('delete form entry action delegates legal holds to the deletion policy', function (): void {
    $form = Form::factory()->create();
    $entry = FormEntry::factory()->for($form)->create(['created_at' => now()->subYears(8)]);
    app()->instance(FormEntryDeletionPolicy::class, new class implements FormEntryDeletionPolicy
    {
        public function authorize(FormEntry $entry, ?Authenticatable $actor = null): void
        {
            throw new Exception('Entry is under legal hold.');
        }
    });
    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Entry is under legal hold.');
    app(FormEntries::class)->delete($entry, actor: formActor());
});
test('deleting an already deleted stale entry cannot decrement another entry counter', function (): void {
    $form = Form::factory()->create(['submissions_count' => 2]);
    $entry = FormEntry::factory()->for($form)->create(['is_spam' => false]);
    $staleEntry = $entry->fresh();
    $action = app(FormEntries::class);
    $action->delete($entry, actor: formActor());
    expect(fn () => $action->delete($staleEntry, actor: formActor()))->toThrow(ModelNotFoundException::class);
    expect($form->fresh()->submissions_count)->toBe(1);
});
test('entry deletion policy receives the current persisted entry state', function (): void {
    $entry = FormEntry::factory()->create(['security_flags' => null]);
    $staleEntry = $entry->fresh();
    $entry->update(['security_flags' => ['legal_hold' => true]]);
    app()->instance(FormEntryDeletionPolicy::class, new class implements FormEntryDeletionPolicy
    {
        public function authorize(FormEntry $entry, ?Authenticatable $actor = null): void
        {
            if ($entry->getSecurityFlag('legal_hold') === true) {
                throw new RuntimeException('Entry is under legal hold.');
            }
        }
    });
    expect(fn () => app(FormEntries::class)->delete($staleEntry, actor: formActor()))->toThrow(RuntimeException::class, 'Entry is under legal hold.');
    $this->assertDatabaseHas(FormsTables::Entries, ['id' => $entry->id]);
});
test('cancelled entry deletion preserves counters and emits no deletion event', function (): void {
    $form = Form::factory()->create(['submissions_count' => 1]);
    $entry = FormEntry::factory()->for($form)->create(['is_spam' => false]);
    Event::listen('eloquent.deleting: '.FormEntry::class, static fn (): bool => false);
    $deletionEvents = 0;
    Event::listen(FormEntryChanged::class, function () use (&$deletionEvents): void {
        $deletionEvents++;
    });
    expect(fn () => app(FormEntries::class)->delete($entry, actor: formActor()))->toThrow(Exception::class);
    expect($form->fresh()->submissions_count)->toBe(1)->and($deletionEvents)->toBe(0);
    $this->assertDatabaseHas(FormsTables::Entries, ['id' => $entry->id]);
});
