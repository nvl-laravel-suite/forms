<?php

declare(strict_types=1);
use Illuminate\Support\Facades\Event;
use Nvl\Forms\Enums\FormType;
use Nvl\Forms\Enums\Resolvement;
use Nvl\Forms\Events\FormChanged;
use Nvl\Forms\Models\AllowedOrigin;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Services\FormLifecycle;
use Nvl\Forms\Tests\Fixtures\Consumer\Data\Mutations\MutateFormPayload;
use Nvl\Translatable\Enums\TranslationSyncMode;

test('update form action updates persisted attributes', function (): void {
    Event::fake([FormChanged::class]);
    $form = Form::factory()->create(['name' => 'Legacy Form', 'handle' => 'legacy-form', 'type' => FormType::LANDING_PAGE]);
    $data = MutateFormPayload::from(['translations' => ['en' => ['name' => 'Updated Form']], 'handle' => 'updated-form', 'type' => FormType::IFRAME->value, 'resolvement' => Resolvement::ENTRIES->value, 'expectedRevision' => $form->revision]);
    $updated = app(FormLifecycle::class)->update($form, formNativeInput($data), actor: formActor());
    expect($updated->displayName())->toBe('Updated Form')->and($updated->handle)->toBe('updated-form')->and($updated->type->value)->toBe(FormType::IFRAME->value)->and($updated->revision)->toBe(2);
    Event::assertDispatched(FormChanged::class);
});
test('update form action preserves omitted attributes and applies explicit null clears', function (): void {
    $form = Form::factory()->create(['name' => 'Existing Form', 'description' => 'Clear me', 'handle' => 'existing-form', 'status' => 'active', 'type' => FormType::IFRAME, 'resolvement' => Resolvement::CUSTOM, 'restrict_public_access' => true, 'allow_multiple_registrations' => false, 'enable_honeypot' => false, 'rate_limit_per_hour' => 25]);
    $updated = app(FormLifecycle::class)->update($form, formNativeInput(MutateFormPayload::from(['translations' => ['en' => ['name' => 'Renamed Form', 'description' => null]], 'expectedRevision' => $form->revision])), actor: formActor());
    expect($updated->displayName())->toBe('Renamed Form')->and($updated->displayDescription())->toBeNull()->and($updated->handle)->toBe('existing-form')->and($updated->status->value)->toBe('active')->and($updated->type)->toBe(FormType::IFRAME)->and($updated->resolvement)->toBe(Resolvement::CUSTOM)->and($updated->restrict_public_access)->toBeTrue()->and($updated->allow_multiple_registrations)->toBeFalse()->and($updated->enable_honeypot)->toBeFalse()->and($updated->rate_limit_per_hour)->toBe(25);
});
test('update form action rejects duplicate handles', function (): void {
    Form::factory()->create(['handle' => 'existing-handle']);
    $form = Form::factory()->create(['handle' => 'original-handle']);
    $data = MutateFormPayload::from(['handle' => 'existing-handle', 'resolvement' => Resolvement::ENTRIES->value, 'type' => FormType::LANDING_PAGE->value, 'expectedRevision' => $form->revision]);
    $this->expectException(Exception::class);
    $this->expectExceptionMessage(trans('nvl-forms::forms/messages.error.handle_exists', ['handle' => 'existing-handle']));
    app(FormLifecycle::class)->update($form, formNativeInput($data), actor: formActor());
});
test('update form action syncs allowed origins by deactivating removed and creating new', function (): void {
    $form = Form::factory()->create(['type' => FormType::IFRAME, 'restrict_public_access' => true]);
    $existing = AllowedOrigin::factory()->create(['form_id' => $form->id, 'origin' => 'old.example.com', 'is_active' => true]);
    $data = MutateFormPayload::from(['translations' => ['en' => ['name' => $form->displayName()]], 'handle' => $form->handle, 'type' => FormType::IFRAME->value, 'resolvement' => Resolvement::ENTRIES->value, 'restrictPublicAccess' => true, 'allowedOrigins' => ['example.com', '*.allowed.com'], 'expectedRevision' => $form->revision]);
    $updated = app(FormLifecycle::class)->update($form->fresh(), formNativeInput($data), actor: formActor());
    $updated->loadMissing('allowedOrigins');
    $active = $updated->allowedOrigins->where('is_active', true)->pluck('origin')->all();
    sort($active);
    expect($active)->toEqual(['*.allowed.com', 'example.com']);
    $existing->refresh();
    expect($existing->is_active)->toBeFalse();
});

test('native form translation patches preserve omitted copy and content', function (): void {
    $service = app(FormLifecycle::class);
    $form = $service->create([
        'translations' => ['en' => ['name' => 'Preserved name', 'description' => 'Clear me', 'fields' => [['key' => 'email']]]],
        'resolvement' => Resolvement::ENTRIES->value,
        'type' => FormType::IFRAME->value,
    ], formActor());

    $updated = $service->update($form, [
        'expectedRevision' => $form->revision,
        'translations' => ['en' => ['description' => null]],
    ], formActor());

    $translation = $updated->translations->firstWhere('locale', 'en');
    expect($translation->name)->toBe('Preserved name')
        ->and($translation->description)->toBeNull()
        ->and($translation->content['fields'])->toBe([['key' => 'email']]);
});

test('native form updates clear explicitly nullable origin lists', function (): void {
    $form = Form::factory()->create();
    $origin = AllowedOrigin::factory()->create(['form_id' => $form->id, 'origin' => 'clear.example.com', 'is_active' => true]);

    app(FormLifecycle::class)->update($form, ['expectedRevision' => $form->revision, 'allowedOrigins' => null], formActor());

    expect($origin->fresh()->is_active)->toBeFalse();
});

test('native form updates honor a translation mode enum', function (): void {
    $service = app(FormLifecycle::class);
    $form = $service->create([
        'translations' => ['en' => ['name' => 'English'], 'bg' => ['name' => 'Bulgarian']],
        'resolvement' => Resolvement::ENTRIES->value,
        'type' => FormType::IFRAME->value,
    ], formActor());

    $updated = $service->update($form, [
        'expectedRevision' => $form->revision,
        'translationMode' => TranslationSyncMode::Replace,
        'translations' => ['en' => ['name' => 'Replacement']],
    ], formActor());

    expect($updated->translations->pluck('locale')->all())->toBe(['en']);
});
