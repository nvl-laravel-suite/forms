<?php

declare(strict_types=1);
use Illuminate\Support\Facades\Event;
use Nvl\Forms\Events\FormEntryChanged;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormEntry;
use Nvl\Forms\Services\FormEntries;
use Nvl\Forms\Tests\Stubs\TestFormsUser;

test('add form entry security flag action persists a flag on the entry', function (): void {
    Event::fake([FormEntryChanged::class]);
    $actor = TestFormsUser::factory()->create(['name' => 'Natali Necheva']);
    $this->actingAs($actor);
    $form = Form::factory()->create();
    $entry = FormEntry::factory()->for($form)->create(['security_flags' => null]);
    $result = app(FormEntries::class)->flag($entry, 'flagged_reason', 'suspicious_timing', actor: formActor());
    expect($result->security_flags)->toBeArray()->and($result->getSecurityFlag('flagged_reason'))->toBe('suspicious_timing');
    Event::assertDispatched(FormEntryChanged::class, static fn (FormEntryChanged $event): bool => $event->operation === 'security_flag_added' && $event->entryId === $entry->id);
});
test('add form entry security flag action preserves existing flags', function (): void {
    $entry = FormEntry::factory()->create(['security_flags' => ['existing_flag' => true]]);
    $result = app(FormEntries::class)->flag($entry, 'new_flag', 'value', actor: formActor());
    expect($result->getSecurityFlag('existing_flag'))->toBeTrue()->and($result->getSecurityFlag('new_flag'))->toBe('value');
});
test('add form entry security flag action resolves entry by id', function (): void {
    $entry = FormEntry::factory()->create(['security_flags' => null]);
    $result = app(FormEntries::class)->flag($entry->id, 'test_key', 42, actor: formActor());
    expect($result->id)->toBe($entry->id)->and($result->getSecurityFlag('test_key'))->toBe(42);
});
test('adding security flags from stale snapshots preserves earlier writes', function (): void {
    $entry = FormEntry::factory()->create(['security_flags' => null]);
    $staleEntry = $entry->fresh();
    app(FormEntries::class)->flag($entry, 'first_flag', true, actor: formActor());
    app(FormEntries::class)->flag($staleEntry, 'second_flag', true, actor: formActor());
    expect($entry->fresh()->security_flags)->toBe(['first_flag' => true, 'second_flag' => true]);
});
