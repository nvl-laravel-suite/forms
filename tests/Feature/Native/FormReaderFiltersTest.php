<?php

declare(strict_types=1);
use Carbon\CarbonImmutable;
use Nvl\Forms\Enums\FormAnalyticEventType;
use Nvl\Forms\Exceptions\FormException;
use Nvl\Forms\Models\AllowedOrigin;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormAnalytic;
use Nvl\Forms\Models\FormEntry;
use Nvl\Forms\Models\FormRateLimit;
use Nvl\Forms\Services\FormEntries;
use Nvl\Forms\Services\FormReader;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-08-20 12:00:00');
});
afterEach(function (): void {
    CarbonImmutable::setTestNow();
});
test('form filters support localized search and last-used presets', function (): void {
    $today = Form::factory()->create(['handle' => 'customer-contact', 'name' => 'Customer Contact', 'last_used_at' => now()]);
    $week = Form::factory()->create(['handle' => 'weekly-report', 'last_used_at' => now()->subDays(2)]);
    $month = Form::factory()->create(['handle' => 'monthly-report', 'last_used_at' => now()->subWeeks(2)]);
    $never = Form::factory()->create(['handle' => 'never-used', 'last_used_at' => null]);
    $model = app(FormReader::class);
    expect($model->paginate(false, filters: ['search' => 'CONTACT'], actor: formActor())->pluck('id')->all())->toBe([$today->id])->and($model->paginate(false, filters: ['search' => ['invalid']], actor: formActor())->count())->toBe(4)->and($model->paginate(false, filters: ['last_used' => 'today'], actor: formActor())->pluck('id')->all())->toBe([$today->id])->and($model->paginate(false, filters: ['last_used' => 'this_week'], actor: formActor())->pluck('id')->all())->toContain($today->id, $week->id)->and($model->paginate(false, filters: ['last_used' => 'this_month'], actor: formActor())->pluck('id')->all())->toContain($today->id, $week->id, $month->id)->and($model->paginate(false, filters: ['last_used' => 'never'], actor: formActor())->pluck('id')->all())->toBe([$never->id]);
    expect(fn () => $model->paginate(false, filters: ['last_used' => 10], actor: formActor()))->toThrow(FormException::class, 'must be a string')->and(fn () => $model->paginate(false, filters: ['last_used' => 'yesterday'], actor: formActor()))->toThrow(FormException::class, 'Unknown last-used');
});
test('form availability filters cover every supported transport shape and operator', function (): void {
    $unrestricted = Form::factory()->create(['handle' => 'always', 'date_restricted' => false]);
    $current = Form::factory()->create(['handle' => 'current', 'date_restricted' => true, 'available_from' => '2026-08-01 00:00:00', 'available_until' => '2026-08-03 00:00:00']);
    $future = Form::factory()->create(['handle' => 'future', 'date_restricted' => true, 'available_from' => '2026-09-01 00:00:00', 'available_until' => null]);
    $model = app(FormReader::class);
    $between = $model->paginate(false, filters: ['availability' => ['2026-08-02 00:00:00', '2026-08-04 00:00:00'], 'availability_operator' => 'between'], actor: formActor())->pluck('id')->all();
    $before = $model->paginate(false, filters: ['availability' => '2026-08-04 00:00:00', 'availability_operator' => 'before'], actor: formActor())->pluck('id')->all();
    $after = $model->paginate(false, filters: ['availability' => CarbonImmutable::parse('2026-08-15 00:00:00'), 'availability_operator' => 'after'], actor: formActor())->pluck('id')->all();
    $equals = $model->paginate(false, filters: ['availability' => '2026-08-02T12:00:00+00:00'], actor: formActor())->pluck('id')->all();
    expect($between)->toContain($unrestricted->id, $current->id)->and($between)->not->toContain($future->id)->and($before)->toContain($unrestricted->id, $current->id)->and($after)->toContain($unrestricted->id, $future->id)->and($equals)->toContain($unrestricted->id, $current->id)->and($equals)->not->toContain($future->id);
    expect(fn () => $model->paginate(false, filters: ['availability' => new stdClass, 'availability_operator' => 'equals'], actor: formActor()))->toThrow(FormException::class, 'must be a date')
        ->and(fn () => $model->paginate(false, filters: ['availability' => 'not-a-valid-date', 'availability_operator' => 'equals'], actor: formActor()))->toThrow(FormException::class, 'must be a date')
        ->and(fn () => $model->paginate(false, filters: ['availability' => '2026-08-02', 'availability_operator' => 'contains'], actor: formActor()))->toThrow(FormException::class, 'Unsupported availability operator');
});

test('form reader searches Cyrillic form translations case-insensitively and hydrates entry flags', function (): void {
    $cyrillic = Form::factory()->create(['handle' => 'bg-contact', 'name' => 'Форма за Контакт']);
    $entry = FormEntry::factory()->for($cyrillic)->create(['subject' => 'Запитване', 'is_spam' => true]);

    expect(app(FormReader::class)->paginate(false, filters: ['search' => 'форма за контакт'], actor: formActor())->pluck('id')->all())
        ->toBe([$cyrillic->id]);

    $listed = app(FormEntries::class)->paginate($cyrillic, false, actor: formActor())->sole();
    expect($listed->is_spam)->toBeTrue()
        ->and(array_key_exists('redacted_at', $listed->getAttributes()))->toBeTrue()
        ->and(array_key_exists('anonymized_at', $listed->getAttributes()))->toBeTrue()
        ->and($listed->id)->toBe($entry->id);
});

test('consumer native queries retain origin entry analytic and rate predicates', function (): void {
    $form = Form::factory()->create(['name' => 'Customer Support']);
    $origin = AllowedOrigin::factory()->for($form)->create(['origin' => 'portal.example.test', 'description' => 'Trusted customer portal']);
    $entry = FormEntry::factory()->for($form)->create(['subject' => 'Priority request', 'email' => 'customer@example.test']);
    $analytic = FormAnalytic::query()->create(['form_id' => $form->id, 'event_type' => FormAnalyticEventType::VIEW, 'origin' => 'portal.example.test', 'ip_address' => '192.0.2.10']);
    $rate = FormRateLimit::query()->create(['form_id' => $form->id, 'ip_address' => '192.0.2.10', 'submission_count' => 2, 'window_start' => now(), 'last_submission_at' => now(), 'is_blocked' => true, 'blocked_until' => now()->addMinute(), 'violation_count' => 1]);
    expect(AllowedOrigin::query()->whereRaw('LOWER(description) LIKE ?', ['%customer%'])->pluck('id')->all())->toBe([$origin->id])
        ->and(app(FormEntries::class)->paginate($form, false, filters: ['email' => 'customer@example.test'], actor: formActor())->pluck('id')->all())->toBe([$entry->id])
        ->and(FormAnalytic::query()->where('event_type', 'view')->pluck('id')->all())->toBe([$analytic->id])
        ->and(FormRateLimit::query()->where('is_blocked', true)->pluck('id')->all())->toBe([$rate->id]);
});
