<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormEntry;
use Nvl\Forms\Services\FormLifecycle;
use Nvl\Forms\Services\FormSubmissions;
use Nvl\Forms\Tests\Stubs\TestFormsUser;

test('native form lifecycle requires explicit authority and preserves revisions and translations', function (): void {
    $input = ['translations' => ['en' => ['name' => 'Native form', 'sections' => [['title' => 'Details']]]], 'resolvement' => 'entries', 'type' => 'iframe'];
    expect(fn () => app(FormLifecycle::class)->create($input))->toThrow(AuthorizationException::class);
    $actor = TestFormsUser::factory()->create();
    $form = app(FormLifecycle::class)->create($input, $actor);
    expect($form->displayName())->toBe('Native form')->and($form->localizedContent()['sections'])->toBe([['title' => 'Details']]);
    $updated = app(FormLifecycle::class)->update($form, ['handle' => 'native-updated', 'expected_revision' => 1], $actor);
    expect($updated->revision)->toBe(2);
});

test('native submissions persist once and reject untrusted protection claims', function (): void {
    $form = Form::factory()->create(['require_csrf' => false, 'enable_honeypot' => false, 'enable_rate_limiting' => false, 'restrict_public_access' => false]);
    $protection = ['origin' => 'https://example.com', 'ipAddress' => '192.0.2.1', 'idempotency_key' => 'native-retry'];
    $service = app(FormSubmissions::class);
    $entry = $service->submit($form, ['email' => 'native@example.com', 'body' => 'A normal enquiry.'], $protection);
    expect($entry)->toBeInstanceOf(FormEntry::class);
    expect($service->submit($form, ['email' => 'native@example.com', 'body' => 'A normal enquiry.'], $protection)->id)->toBe($entry->id);
    expect(FormEntry::query()->count())->toBe(1);
    expect(fn () => $service->submit($form, [], [...$protection, 'trustedFormLoadTime' => 1]))->toThrow(ValidationException::class);
});
