<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Nvl\Forms\Contracts\FormEntryPrivacyPolicy;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormEntry;
use Nvl\Forms\Policies\FormEntryPolicy;
use Nvl\Forms\Policies\FormPolicy;
use Nvl\Forms\Services\FormEntries;
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

test('native submissions reject unknown and protected root fields before persistence', function (string $field): void {
    $form = Form::factory()->create(['require_csrf' => false, 'enable_honeypot' => false, 'enable_rate_limiting' => false, 'restrict_public_access' => false]);

    expect(fn () => app(FormSubmissions::class)->submit($form, [
        'email' => 'native@example.com', $field => 'untrusted',
    ], ['origin' => 'https://example.com', 'ipAddress' => '192.0.2.1']))
        ->toThrow(ValidationException::class);

    expect(FormEntry::query()->count())->toBe(0)
        ->and($form->fresh()?->submissions_count)->toBe(0);
})->with(['owner_scope_key', 'actor', 'revision', 'emial']);

test('native management admits current canonical actor facts', function (string $mutation): void {
    $actor = TestFormsUser::factory()->create(['name' => $mutation === 'revoked' ? 'Allowed' : 'Denied']);
    Gate::define('manage-forms', static fn (TestFormsUser $actor): bool => $actor->name === 'Allowed' || $actor->relationLoaded('grant'));
    if ($mutation === 'relation') {
        $actor->setRelation('grant', new TestFormsUser);
    } elseif ($mutation === 'revoked') {
        TestFormsUser::query()->whereKey($actor->getKey())->update(['name' => 'Denied']);
    } else {
        $actor->name = 'Allowed';
        if ($mutation === 'transient') {
            $actor->exists = false;
        } elseif ($mutation === 'table') {
            $actor->setTable('forged_actors');
        } elseif ($mutation === 'identity') {
            $actor->id++;
        }
    }

    expect(fn () => app(FormLifecycle::class)->create([
        'translations' => ['en' => ['name' => 'Rejected']], 'resolvement' => 'entries', 'type' => 'iframe',
    ], $actor))->toThrow(AuthorizationException::class);
    expect(Form::query()->count())->toBe(0)
        ->and(app(FormPolicy::class)->viewAny($actor))->toBeFalse()
        ->and(app(FormEntryPolicy::class)->viewAny($actor))->toBeFalse();
})->with(['attribute', 'relation', 'revoked', 'transient', 'table', 'identity']);

test('native entry privacy policies receive the canonical management actor', function (): void {
    $actor = TestFormsUser::factory()->create(['name' => 'Persisted manager']);
    $actor->name = 'Forged manager';
    $actor->setRelation('grant', new TestFormsUser);
    $entry = FormEntry::factory()->create(['email' => 'person@example.test']);
    app()->instance(FormEntryPrivacyPolicy::class, new class implements FormEntryPrivacyPolicy
    {
        public function authorize(string $operation, FormEntry $entry, ?Authenticatable $actor = null): void
        {
            expect($actor?->name)->toBe('Persisted manager')
                ->and($actor?->relationLoaded('grant'))->toBeFalse();
        }
    });

    expect(app(FormEntries::class)->redact($entry, ['email'], $actor)->email)->toBeNull();
});

test('native management retains explicit non Eloquent provider identities', function (): void {
    $actor = new GenericUser(['id' => 'external-manager', 'name' => 'Provider manager']);
    Gate::define('manage-forms', static fn (Authenticatable $current): bool => $current === $actor);

    $form = app(FormLifecycle::class)->create([
        'translations' => ['en' => ['name' => 'External actor']], 'resolvement' => 'entries', 'type' => 'iframe',
    ], $actor);

    expect($form->displayName())->toBe('External actor');
});
