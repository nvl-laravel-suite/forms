<?php

declare(strict_types=1);
use Illuminate\Support\Facades\Schema;
use Nvl\Forms\Definitions\Tables\FormsTables;
use Nvl\Forms\Enums\FormType;
use Nvl\Forms\Enums\Resolvement;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormTranslation;
use Nvl\Forms\Services\FormLifecycle;
use Nvl\Forms\Tests\Fixtures\Consumer\Data\Mutations\MutateFormPayload;
use Nvl\Forms\Tests\Fixtures\Consumer\Presentation\TransformFormDataForRenderAction;
use Nvl\Translatable\Enums\TranslationSyncMode;
use Nvl\Translatable\Services\ContentLocale;

beforeEach(function (): void {
    config(['nvl-translatable.locales' => ['en', 'bg', 'fr'], 'nvl-translatable.fallback_locales' => ['en'], 'app.fallback_locale' => 'en']);
});
test('form copy and arbitrary content resolve through deterministic field fallback', function (): void {
    $form = app(FormLifecycle::class)->create(formNativeInput(MutateFormPayload::from(['resolvement' => Resolvement::ENTRIES->value, 'type' => FormType::LANDING_PAGE->value, 'translations' => ['en' => ['name' => 'Registration', 'description' => 'English description', 'submitButtonLabel' => 'Send', 'fields' => ['email' => ['label' => 'Email']]], 'bg' => ['name' => 'Регистрация', 'fields' => ['email' => ['label' => 'Имейл']]]]])), actor: formActor());
    $form->setLocale('bg');
    expect($form->displayName())->toBe('Регистрация')->and($form->displayDescription())->toBe('English description')->and($form->translated('submit_button_label'))->toBe('Send')->and($form->localizedContent())->toMatchArray(['name' => 'Регистрация', 'fields' => ['email' => ['label' => 'Имейл']]]);
    app(ContentLocale::class)->set('bg');
    $render = app(TransformFormDataForRenderAction::class)->execute($form);
    expect($render->locale)->toBe('bg')->and($render->name)->toBe('Регистрация')->and($render->description)->toBe('English description')->and($render->submitButtonLabel)->toBe('Send');
});
test('form translation mutations patch by default and replace only omitted non-base locales', function (): void {
    $form = app(FormLifecycle::class)->create(formNativeInput(MutateFormPayload::from(['resolvement' => Resolvement::ENTRIES->value, 'type' => FormType::LANDING_PAGE->value, 'translations' => ['en' => ['name' => 'Base form'], 'bg' => ['name' => 'Базова форма'], 'fr' => ['name' => 'Formulaire de base']]])), actor: formActor());
    app(FormLifecycle::class)->update($form, formNativeInput(MutateFormPayload::from(['resolvement' => Resolvement::ENTRIES->value, 'type' => FormType::LANDING_PAGE->value, 'translations' => ['bg' => ['name' => 'Променена форма']], 'expected_revision' => $form->revision])), actor: formActor());
    expect($form->fresh()->getAvailableLocales())->toEqualCanonicalizing(['en', 'bg', 'fr']);
    $form->refresh();
    app(FormLifecycle::class)->update($form, formNativeInput(MutateFormPayload::from(['resolvement' => Resolvement::ENTRIES->value, 'type' => FormType::LANDING_PAGE->value, 'translations' => ['bg' => ['name' => 'Променена форма']], 'translation_mode' => TranslationSyncMode::Replace->value, 'expected_revision' => $form->revision])), actor: formActor());
    expect($form->fresh()->getAvailableLocales())->toEqualCanonicalizing(['bg']);
});
test('form factory stores localized content only in dedicated rows', function (): void {
    $form = Form::factory()->create(['name' => 'Legacy form', 'description' => 'Legacy description', 'translations' => ['bg' => ['name' => 'Стара форма', 'fields' => ['email' => ['label' => 'Имейл']]]]]);
    $translation = FormTranslation::query()->where('form_id', $form->id)->where('locale', 'bg')->firstOrFail();
    expect($translation->name)->toBe('Стара форма')->and($translation->content)->toMatchArray(['fields' => ['email' => ['label' => 'Имейл']]])->and(Schema::hasColumn(FormsTables::Forms, 'translations'))->toBeFalse();
});
