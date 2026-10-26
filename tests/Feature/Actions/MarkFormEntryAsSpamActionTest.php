<?php

declare(strict_types=1);
use Illuminate\Support\Facades\Event;
use Nvl\Forms\Events\FormEntryChanged;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormEntry;
use Nvl\Forms\Services\FormEntries;

test('mark form entry as spam action sets is_spam and increments form counter', function (): void {
    Event::fake([FormEntryChanged::class]);
    $form = Form::factory()->create(['spam_count' => 0]);
    $entry = FormEntry::factory()->for($form)->create(['is_spam' => false]);
    $result = app(FormEntries::class)->markSpam($entry, actor: formActor());
    expect($result->is_spam)->toBeTrue()->and($result->getSecurityFlag('marked_spam_at'))->not->toBeNull()->and($result->form->spam_count)->toBe(1);
    Event::assertDispatched(FormEntryChanged::class);
});
test('mark form entry as spam action stores optional reason', function (): void {
    $form = Form::factory()->create(['spam_count' => 0]);
    $entry = FormEntry::factory()->for($form)->create(['is_spam' => false]);
    $result = app(FormEntries::class)->markSpam($entry, 'obvious bot pattern', actor: formActor());
    expect($result->getSecurityFlag('spam_reason'))->toBe('obvious bot pattern');
});
test('mark form entry as spam action resolves entry by id', function (): void {
    $form = Form::factory()->create(['spam_count' => 2]);
    $entry = FormEntry::factory()->for($form)->create(['is_spam' => false]);
    $result = app(FormEntries::class)->markSpam($entry->id, actor: formActor());
    expect($result->is_spam)->toBeTrue()->and($form->fresh()->spam_count)->toBe(3);
});
test('marking spam from stale snapshots counts the transition only once', function (): void {
    $form = Form::factory()->create(['spam_count' => 0]);
    $entry = FormEntry::factory()->for($form)->create(['is_spam' => false]);
    $staleEntry = $entry->fresh();
    app(FormEntries::class)->markSpam($entry, 'first reason', actor: formActor());
    app(FormEntries::class)->markSpam($staleEntry, actor: formActor());
    expect($form->fresh()->spam_count)->toBe(1)->and($entry->fresh()->getSecurityFlag('spam_reason'))->toBe('first reason');
});
