<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Nvl\Forms\Contracts\FormEntryPrivacyPolicy;
use Nvl\Forms\Exceptions\FormSubmissionRejectionException;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormEntry;
use Nvl\Forms\Models\FormRateLimit;
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

test('native form date patches validate the resulting window and retain omitted boundaries', function (string $field, ?string $value): void {
    $form = Form::factory()->create([
        'revision' => 1, 'date_restricted' => true,
        'available_from' => '2030-01-10 10:00:00', 'available_until' => '2030-01-20 10:00:00',
    ]);

    $updated = app(FormLifecycle::class)->update($form, ['expected_revision' => 1, $field => $value], formActor());

    expect($updated->available_from?->toDateTimeString())->toBe($field === 'availableFrom' ? $value : '2030-01-10 10:00:00')
        ->and($updated->available_until?->toDateTimeString())->toBe($field === 'availableUntil' ? $value : '2030-01-20 10:00:00')
        ->and($updated->revision)->toBe(2);
})->with([
    'start only' => ['availableFrom', '2030-01-11 10:00:00'],
    'end only' => ['availableUntil', '2030-01-21 10:00:00'],
    'clear start' => ['availableFrom', null],
    'clear end' => ['availableUntil', null],
]);

test('native form date patches reject reversed windows without changing the form', function (string $field, string $value): void {
    $form = Form::factory()->create([
        'revision' => 1, 'date_restricted' => true,
        'available_from' => '2030-01-10 10:00:00', 'available_until' => '2030-01-20 10:00:00',
    ]);

    expect(fn () => app(FormLifecycle::class)->update($form, ['expected_revision' => 1, $field => $value], formActor()))
        ->toThrow(ValidationException::class);
    $form->refresh();
    expect($form->available_from?->toDateTimeString())->toBe('2030-01-10 10:00:00')
        ->and($form->available_until?->toDateTimeString())->toBe('2030-01-20 10:00:00')
        ->and($form->revision)->toBe(1);
})->with([
    'start after retained end' => ['availableFrom', '2030-01-21 10:00:00'],
    'end before retained start' => ['availableUntil', '2030-01-09 10:00:00'],
]);

test('native form date patches reject enabling a reversed stored window', function (): void {
    $form = Form::factory()->create([
        'revision' => 1, 'date_restricted' => false,
        'available_from' => '2030-01-21 10:00:00', 'available_until' => '2030-01-20 10:00:00',
    ]);

    expect(fn () => app(FormLifecycle::class)->update($form, ['expected_revision' => 1, 'dateRestricted' => true], formActor()))
        ->toThrow(ValidationException::class);
    expect($form->fresh()?->date_restricted)->toBeFalse()
        ->and($form->fresh()?->revision)->toBe(1);
});

test('native form creation accepts open or equal date boundaries', function (?string $from, ?string $until): void {
    $form = app(FormLifecycle::class)->create([
        'translations' => ['en' => ['name' => 'Scheduled form']], 'resolvement' => 'entries', 'type' => 'iframe',
        'dateRestricted' => true, 'availableFrom' => $from, 'availableUntil' => $until,
    ], formActor());

    expect($form->available_from?->toDateTimeString())->toBe($from)
        ->and($form->available_until?->toDateTimeString())->toBe($until);
})->with([
    'open start' => [null, '2030-01-20 10:00:00'],
    'open end' => ['2030-01-10 10:00:00', null],
    'equal' => ['2030-01-10 10:00:00', '2030-01-10 10:00:00'],
]);

test('native form creation rejects reversed date boundaries before persistence', function (): void {
    expect(fn () => app(FormLifecycle::class)->create([
        'translations' => ['en' => ['name' => 'Invalid schedule']], 'resolvement' => 'entries', 'type' => 'iframe',
        'dateRestricted' => true, 'availableFrom' => '2030-01-21 10:00:00', 'availableUntil' => '2030-01-20 10:00:00',
    ], formActor()))->toThrow(ValidationException::class);
    expect(Form::query()->count())->toBe(0);
});

test('native form availability resolves offsets to the application timezone before storage', function (): void {
    $form = app(FormLifecycle::class)->create([
        'translations' => ['en' => ['name' => 'Offset schedule']], 'resolvement' => 'entries', 'type' => 'iframe',
        'dateRestricted' => true, 'availableFrom' => '2026-01-01T10:00:00+02:00', 'availableUntil' => '2026-01-01T09:00:00Z',
    ], formActor());

    expect(config('app.timezone'))->toBe('UTC')
        ->and($form->available_from?->toIso8601String())->toBe('2026-01-01T08:00:00+00:00')
        ->and($form->available_until?->toIso8601String())->toBe('2026-01-01T09:00:00+00:00');

    $updated = app(FormLifecycle::class)->update($form, ['expected_revision' => 1, 'availableUntil' => '2026-01-01T12:30:00+03:00'], formActor());

    expect($updated->available_until?->toIso8601String())->toBe('2026-01-01T09:30:00+00:00');
});

test('native form availability rejects windows reversed across offsets', function (): void {
    expect(fn () => app(FormLifecycle::class)->create([
        'translations' => ['en' => ['name' => 'Reversed offsets']], 'resolvement' => 'entries', 'type' => 'iframe',
        'dateRestricted' => true, 'availableFrom' => '2026-01-01T10:00:00Z', 'availableUntil' => '2026-01-01T11:00:00+02:00',
    ], formActor()))->toThrow(ValidationException::class);
    expect(Form::query()->count())->toBe(0);
});

test('native form rate limits stay within the stored integer range', function (): void {
    expect(fn () => app(FormLifecycle::class)->create([
        'translations' => ['en' => ['name' => 'Oversized limit']], 'resolvement' => 'entries', 'type' => 'iframe',
        'rateLimitPerHour' => 2147483648,
    ], formActor()))->toThrow(ValidationException::class);
    expect(Form::query()->count())->toBe(0);
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

test('entry rejection retains rate limit blocking state', function (): void {
    $form = Form::factory()->create(['require_csrf' => false, 'enable_honeypot' => false, 'enable_rate_limiting' => true, 'rate_limit_per_hour' => 1, 'restrict_public_access' => false]);
    $service = app(FormSubmissions::class);
    $protection = ['origin' => 'https://example.com', 'ipAddress' => '192.0.2.70'];
    FormRateLimit::factory()->create([
        'form_id' => $form->id, 'ip_address' => '192.0.2.70', 'submission_count' => 1,
        'window_start' => now(), 'last_submission_at' => now(), 'is_blocked' => false,
        'blocked_until' => null, 'violation_count' => 0,
    ]);

    expect(fn () => $service->submit($form, ['body' => 'Excess enquiry.'], $protection))
        ->toThrow(FormSubmissionRejectionException::class);
    $limit = FormRateLimit::query()->where('form_id', $form->id)->sole();
    expect($limit->is_blocked)->toBeTrue()
        ->and($limit->violation_count)->toBe(1)
        ->and(FormEntry::query()->where('form_id', $form->id)->count())->toBe(0);
});

test('entry honeypot returns the native submission rejection type', function (): void {
    config(['nvl-forms.security.spam_protection.honeypot.field_names' => ['website']]);
    $form = Form::factory()->create(['require_csrf' => false, 'enable_honeypot' => true, 'enable_rate_limiting' => false, 'restrict_public_access' => false]);

    expect(fn () => app(FormSubmissions::class)->submit($form, ['submissionData' => ['website' => 'spam']], [
        'origin' => 'https://example.com', 'ipAddress' => '192.0.2.71',
    ]))->toThrow(FormSubmissionRejectionException::class);
    expect(FormEntry::query()->where('form_id', $form->id)->count())->toBe(0);
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

dataset('native submission encoders', [
    'arrays' => [static fn (array $profile): array => $profile],
    'objects' => [static fn (array $profile): object => json_decode(json_encode($profile, JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR)],
    'JSON serializable' => [static fn (array $profile): JsonSerializable => new class($profile) implements JsonSerializable
    {
        /** @param array<string, mixed> $profile */
        public function __construct(private readonly array $profile) {}

        /** @return array<string, mixed> */
        public function jsonSerialize(): array
        {
            return $this->profile;
        }
    }],
]);

test('native submission payload limits include serialized descendants before writes', function (Closure $encode, string $limit, string $operation): void {
    config([
        'nvl-forms.submission.max_depth' => $limit === 'depth' ? 2 : 8,
        'nvl-forms.submission.max_items' => $limit === 'items' ? 2 : 250,
    ]);
    $form = Form::factory()->create(['require_csrf' => false, 'enable_honeypot' => false, 'enable_rate_limiting' => false, 'restrict_public_access' => false]);
    $entry = $operation === 'update' ? FormEntry::factory()->create(['form_id' => $form->id, 'submission_data' => ['original' => true]]) : null;
    $profile = $limit === 'depth' ? ['details' => ['name' => 'Ada']] : ['first' => 'Ada', 'last' => 'Lovelace'];
    $fields = ['body' => 'A normal enquiry.', 'submissionData' => ['profile' => $encode($profile)]];

    expect(fn () => $entry === null
        ? app(FormSubmissions::class)->submit($form, $fields, ['origin' => 'https://example.com', 'ipAddress' => '192.0.2.1'])
        : app(FormEntries::class)->update($entry, $fields, formActor()))
        ->toThrow(ValidationException::class);

    expect(FormEntry::query()->count())->toBe($entry === null ? 0 : 1);
    if ($entry !== null) {
        expect($entry->fresh()?->submission_data)->toBe(['original' => true]);
    }
})->with('native submission encoders')->with(['depth', 'items'])->with(['submit', 'update']);

test('native submissions and entry updates retain serialized payloads within the limits', function (Closure $encode): void {
    config(['nvl-forms.submission.max_depth' => 2, 'nvl-forms.submission.max_items' => 3]);
    $form = Form::factory()->create(['require_csrf' => false, 'enable_honeypot' => false, 'enable_rate_limiting' => false, 'restrict_public_access' => false]);
    $entry = app(FormSubmissions::class)->submit($form, [
        'body' => 'A normal enquiry.', 'submissionData' => ['profile' => $encode(['first' => 'Ada', 'last' => 'Lovelace'])],
    ], ['origin' => 'https://example.com', 'ipAddress' => '192.0.2.1']);

    expect($entry)->toBeInstanceOf(FormEntry::class)
        ->and($entry->fresh()?->submission_data['profile'])->toEqual(['first' => 'Ada', 'last' => 'Lovelace']);

    $updated = app(FormEntries::class)->update($entry, [
        'submissionData' => ['profile' => $encode(['first' => 'Grace', 'last' => 'Hopper'])],
    ], formActor());
    expect($updated->fresh()?->submission_data['profile'])->toEqual(['first' => 'Grace', 'last' => 'Hopper'])
        ->and(FormEntry::query()->count())->toBe(1);
})->with('native submission encoders');

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

test('native entry updates reject text that public submission rejects and keep stored content', function (string $subject): void {
    $form = Form::factory()->create();
    $entry = FormEntry::factory()->create(['form_id' => $form->id, 'subject' => 'Original subject']);

    expect(fn () => app(FormEntries::class)->update($entry, ['subject' => $subject], formActor()))
        ->toThrow(ValidationException::class, 'Submission text must be valid UTF-8 without NUL bytes.');
    expect($entry->fresh()?->subject)->toBe('Original subject');
})->with(['nul byte' => "quiet\0subject", 'malformed utf-8' => "quiet\xC3\x28subject"]);

test('native submission origins must fit the stored submitted_from column', function (int $hostLength, bool $accepted): void {
    $form = Form::factory()->create(['require_csrf' => false, 'enable_honeypot' => false, 'enable_rate_limiting' => false, 'restrict_public_access' => false]);
    $labels = str_split(str_repeat('a', $hostLength - intdiv($hostLength, 64)), 63);
    $host = implode('.', $labels);
    expect(strlen($host))->toBe($hostLength);
    $submit = fn () => app(FormSubmissions::class)->submit($form, ['body' => 'A normal enquiry.'], ['origin' => "https://{$host}:8443", 'ipAddress' => '192.0.2.1']);

    if ($accepted) {
        expect($submit()->fresh()?->submitted_from)->toBe("{$host}:8443");
    } else {
        expect($submit)->toThrow(ValidationException::class, 'A canonical HTTP origin is required.');
        expect(FormEntry::query()->count())->toBe(0);
    }
})->with(['253-character host with a port' => [253, false], '250-character host with a port' => [250, true]]);
