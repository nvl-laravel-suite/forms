<?php

declare(strict_types=1);

namespace Nvl\Forms\Services;

use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Nvl\Forms\Enums\CorsPolicy;
use Nvl\Forms\Enums\FormStatus;
use Nvl\Forms\Enums\FormType;
use Nvl\Forms\Enums\Resolvement;
use Nvl\Forms\Events\FormChanged;
use Nvl\Forms\Exceptions\FormException;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormTranslation;
use Nvl\Forms\Ownership\FormsOwnership;
use Nvl\Forms\Rules\AllowedOriginExpressionRule;
use Nvl\Support\Events\DomainEventDispatcher;
use Nvl\Translatable\Enums\TranslationSyncMode;
use Nvl\Translatable\Rules\SupportedLocaleMapRule;
use Nvl\Translatable\Services\RelatedTranslationStore;
use Nvl\Translatable\Services\TranslationPayloadValidator;
use Throwable;

/**
 * Native Forms workflow family.
 *
 * @api
 */
final class FormLifecycle
{
    public function __construct(private readonly FormHandleService $handleService, private readonly FormAllowedOriginService $originService, private readonly FormTranslationPayloadMapper $translationPayloadMapper, private readonly RelatedTranslationStore $translationWriter, private DomainEventDispatcher $domainEvents) {}

    /**
     * Execute the form creation within a database transaction.
     *
     * Generates a unique handle if not provided, persists the form,
     * creates any allowed origins, and returns the fresh model.
     *
     * @param  array<string, mixed>  $data  Validated form mutation data
     * @param  Authenticatable|null  $actor  Authenticated actor performing the operation
     * @return Form The created form instance with allowedOrigins loaded
     *
     * @throws Exception When handle already exists or model refresh fails
     * @throws Throwable When transaction fails
     */
    public function create(array $data, ?Authenticatable $actor = null): Form
    {
        FormManagement::authorize($actor);
        $this->validateMutationKeys($data);
        Validator::make($data, $this->mutationRules(false))->validate();
        $data = array_intersect_key($data, $this->mutationRules(false));
        $form = FormModelRegistry::newInstance()->getConnection()->transaction(function () use ($data, $actor) {
            $translations = is_array($data['translations'] ?? null) ? $data['translations'] : [];
            $formData = $this->attributes($data, ['allowedOrigins', 'translations', 'translation_mode', 'expected_revision']);
            $translationRows = $this->translationPayloadMapper->rows(array_filter($translations, is_string(...), ARRAY_FILTER_USE_KEY));
            // Generate handle if not provided
            $handle = $formData['handle'] ?? null;
            if (! is_string($handle) || $handle === '') {
                $handle = $this->handleService->generateHandle($this->createFormFirstTranslationName($translationRows));
                $formData['handle'] = $handle;
            }
            $this->handleService->validateUniqueness($handle);
            $form = FormModelRegistry::create($formData);
            if (is_array($data['allowedOrigins'] ?? null)) {
                $this->originService->createOrigins($form, array_values($data['allowedOrigins']));
            }
            $this->syncTranslations($form, $translationRows, $this->translationMode($data));
            $freshForm = $form->fresh();
            if ($freshForm === null) {
                throw new Exception((string) trans('nvl-forms::forms/shared.messages.error.refresh_failed', ['item' => (string) trans('nvl-forms::forms/general.entities.singular')]));
            }
            $this->domainEvents->dispatch(FormChanged::for($form, 'created', $actor), $form->getConnection());

            return $freshForm->loadMissing(['allowedOrigins', 'translations']);
        });

        return $form;
    }

    /**
     * @param  array<string, array<string, mixed>>  $translations
     */
    private function createFormFirstTranslationName(array $translations): ?string
    {
        foreach ($translations as $translation) {
            $name = $translation['name'] ?? null;
            if (is_string($name) && trim($name) !== '') {
                return $name;
            }
        }

        return null;
    }

    /**
     * Execute the form update within a database transaction.
     *
     * Validates handle uniqueness if changed, updates form attributes,
     * syncs allowed origins, and returns the refreshed model.
     *
     * @param  Form|string  $form  Form instance or identifier
     * @param  array<string, mixed>  $data  Updated form mutation data
     * @param  Authenticatable|null  $actor  Authenticated actor performing the update
     * @return Form Updated form instance with allowedOrigins loaded
     *
     * @throws Exception When handle is not unique
     * @throws Throwable When transaction fails
     */
    public function update(Form|string $form, array $data, ?Authenticatable $actor = null): Form
    {
        FormManagement::authorize($actor);
        $this->validateMutationKeys($data);
        Validator::make($data, $this->mutationRules(true))->validate();
        $data = array_intersect_key($data, $this->mutationRules(true));
        $formId = $form instanceof Form ? $form->id : $form;
        $updated = FormModelRegistry::newInstance()->getConnection()->transaction(function () use ($formId, $data, $actor) {
            $form = FormModelRegistry::query()->lockForUpdate()->findOrFail($formId);
            $expectedRevision = $data['expected_revision'] ?? null;
            if ($expectedRevision === null || $form->revision !== $expectedRevision) {
                throw new FormException('The form was changed by another writer.', 409);
            }
            // Validate handle uniqueness if changed
            if (is_string($data['handle'] ?? null) && $form->handle !== $data['handle']) {
                $this->handleService->validateUniqueness($data['handle'], $form->id);
            }
            // Update form data
            $payload = $this->attributes($data, ['translation_mode', 'translations', 'expected_revision']);
            $allowedOrigins = $payload['allowed_origins'] ?? null;
            unset($payload['allowed_origins']);
            $translations = is_array($data['translations'] ?? null) ? $data['translations'] : null;
            $payload['revision'] = $form->revision + 1;
            $form->fill($payload);
            $form->save();
            if (array_key_exists('allowedOrigins', $data)) {
                $this->originService->syncOrigins($form, is_array($allowedOrigins) ? array_values($allowedOrigins) : []);
            }
            $translationRows = $this->translationPayloadMapper->rows($translations === null ? null : array_filter($translations, is_string(...), ARRAY_FILTER_USE_KEY));
            if ($translationRows !== [] || $this->translationMode($data)->value === 'replace') {
                $this->syncTranslations($form, $translationRows, $this->translationMode($data));
            }
            $form->refresh();
            $form->loadMissing(['allowedOrigins', 'translations']);
            $this->domainEvents->dispatch(FormChanged::for($form, 'updated', $actor), $form->getConnection());

            return $form;
        });

        return $updated;
    }

    /**
     * Execute the form duplication within a database transaction.
     *
     * Replicates the original form, generates a unique handle, resets statistics
     * duplicates allowed origins, and records domain activities.
     *
     * @param  Form|string  $form  Form instance or identifier to duplicate
     * @param  string|null  $newName  Optional new name for the duplicated form
     * @param  Authenticatable|null  $actor  Actor performing the duplication
     * @return Form The duplicated form instance with allowedOrigins loaded
     *
     * @throws Exception When duplication fails
     * @throws Throwable When transaction fails
     */
    public function duplicate(Form|string $form, ?string $newName = null, ?Authenticatable $actor = null): Form
    {
        FormManagement::authorize($actor);
        $originalForm = $form instanceof Form ? $form->load(['allowedOrigins', 'translations']) : FormModelRegistry::query()->with(['allowedOrigins', 'translations'])->findOrFail($form);
        if (FormsOwnership::declared()) {
            $originalForm = FormModelRegistry::query()->with(['allowedOrigins', 'translations'])->findOrFail($originalForm->identifier());
        }
        /** @var Form $newForm */
        $newForm = FormModelRegistry::newInstance()->getConnection()->transaction(function () use ($originalForm, $newName, $actor) {
            $newForm = $originalForm->replicate();
            $sourceName = $originalForm->displayName();
            $duplicateName = $newName ?? $sourceName.' (Copy)';
            $newForm->handle = $this->handleService->generateUniqueHandle($duplicateName);
            $newForm->status = FormStatus::DRAFT;
            $newForm->revision = 1;
            // Reset all statistics and usage data
            $newForm->submissions_count = 0;
            $newForm->views_count = 0;
            $newForm->spam_count = 0;
            $newForm->last_used_at = null;
            $newForm->first_used_at = null;
            $newForm = FormModelRegistry::persist($newForm);
            $this->duplicateFormDuplicateAllowedOrigins($originalForm, $newForm);
            $this->duplicateFormDuplicateTranslations($originalForm, $newForm, $newName);
            $newForm->load(['allowedOrigins', 'translations']);
            $this->domainEvents->dispatch(FormChanged::for(form: $newForm, operation: 'duplicated', actor: $actor, context: ['source_form_id' => $originalForm->id]), $newForm->getConnection());

            return $newForm;
        });

        return $newForm;
    }

    /**
     * Duplicate allowed origins with fresh usage statistics.
     *
     * Replicates each origin record, assigns it to the new form,
     * and resets usage_count and last_used_at.
     *
     * @param  Form  $originalForm  Source form with loaded allowedOrigins
     * @param  Form  $newForm  Target form to attach duplicated origins to
     */
    private function duplicateFormDuplicateAllowedOrigins(Form $originalForm, Form $newForm): void
    {
        foreach ($originalForm->allowedOrigins as $originalOrigin) {
            $newOrigin = $originalOrigin->replicate();
            $newOrigin->form_id = $newForm->id;
            $newOrigin->usage_count = 0;
            $newOrigin->last_used_at = null;
            if (FormsOwnership::declared()) {
                $newOrigin->offsetUnset('owner_scope_key');
            }
            $newOrigin->saveQuietly();
        }
    }

    /**
     * Duplicate every locale row while applying the explicit replacement name when supplied.
     */
    private function duplicateFormDuplicateTranslations(Form $originalForm, Form $newForm, ?string $newName): void
    {
        $fields = $originalForm->translationDefinition()->fields;
        /** @var array<string, array<string, mixed>> $translations */
        $translations = $originalForm->translations->mapWithKeys(function (FormTranslation $translation) use ($fields, $newName): array {
            $attributes = $translation->only($fields);
            if ($newName !== null) {
                $attributes['name'] = $newName;
            }

            return [$translation->locale => $attributes];
        })->all();
        $this->syncTranslations($newForm, $translations, TranslationSyncMode::Replace);
    }

    /**
     * Execute the form deletion.
     *
     * @param  Form|string  $form  Form instance or identifier
     * @param  Authenticatable|null  $actor  Authenticated actor performing the deletion
     * @return bool Deletion success
     *
     * @throws Exception|Throwable If form not found or has dependencies
     */
    public function delete(Form|string $form, ?Authenticatable $actor = null): bool
    {
        FormManagement::authorize($actor);
        // Resolve model if ID provided
        $form = $form instanceof Form ? $form : FormModelRegistry::query()->findOrFail($form);
        $deleted = FormModelRegistry::newInstance()->getConnection()->transaction(function () use ($form, $actor) {
            // Check for dependencies (form entries)
            if ($form->entries()->exists()) {
                throw new Exception((string) trans('nvl-forms::forms/messages.error.cannot_delete_with_entries'));
            }
            // Perform deletion
            $deleted = $form->delete();
            if ($deleted === null) {
                throw new Exception((string) trans('nvl-forms::forms/shared.messages.error.delete_failed', ['item' => (string) trans('nvl-forms::forms/general.entities.singular')]));
            }
            $this->domainEvents->dispatch(FormChanged::for($form, 'deleted', $actor), $form->getConnection());

            return $deleted;
        });

        return $deleted;
    }

    /** @return array<string, array<int, mixed>> */
    private function mutationRules(bool $updating): array
    {
        $presenceRule = $updating ? 'sometimes' : 'required';

        return [
            'handle' => ['nullable', 'string', 'max:255'],
            'translations' => [$presenceRule, 'array', new SupportedLocaleMapRule],
            'translations.*' => ['nullable', 'array'],
            'translations.*.name' => ['nullable', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string', 'max:2000'],
            'translations.*.submitButtonLabel' => ['nullable', 'string', 'max:100'],
            'translations.*.successTitle' => ['nullable', 'string', 'max:255'],
            'translations.*.successMessage' => ['nullable', 'string', 'max:5000'],
            'translation_mode' => ['sometimes', Rule::enum(TranslationSyncMode::class)],
            'expected_revision' => [$updating ? 'required' : 'prohibited', 'integer', 'min:1'],
            'resolvement' => [$presenceRule, Rule::enum(Resolvement::class)],
            'type' => [$presenceRule, Rule::enum(FormType::class)],
            'status' => ['sometimes', Rule::enum(FormStatus::class)],
            'restrictPublicAccess' => ['boolean'],
            'allowMultipleRegistrations' => ['boolean'],
            'dateRestricted' => ['boolean'],
            'availableFrom' => ['nullable', 'date'],
            'availableUntil' => ['nullable', 'date', 'after_or_equal:availableFrom'],
            'enableHoneypot' => ['boolean'],
            'enableRateLimiting' => ['boolean'],
            'rateLimitPerHour' => ['nullable', 'integer', 'min:1'],
            'requireCsrf' => ['boolean'],
            'corsSettings' => ['nullable', 'array:policy,allowCredentials,allowWildcards,maxAge,allowedMethods,allowedHeaders'],
            'corsSettings.policy' => [Rule::enum(CorsPolicy::class)],
            'corsSettings.allowCredentials' => ['boolean'],
            'corsSettings.allowWildcards' => ['boolean'],
            'corsSettings.maxAge' => ['integer', 'min:0', 'max:86400'],
            'corsSettings.allowedMethods' => ['array', 'min:1'],
            'corsSettings.allowedMethods.*' => ['string', 'distinct', Rule::in(['GET', 'POST', 'OPTIONS'])],
            'corsSettings.allowedHeaders' => ['array', 'min:1'],
            'corsSettings.allowedHeaders.*' => ['string', 'distinct', 'regex:/^(?:\*|[A-Za-z0-9!#$%&\\\'*+.^_`|~-]+)$/'],
            'options' => ['nullable', 'array'],
            'options.showHeader' => ['boolean'],
            'options.showLogo' => ['boolean'],
            'options.submitButtonLabel' => ['nullable', 'string', 'max:100'],
            'options.successRedirectUrl' => ['nullable', 'string', 'url', 'max:500'],
            'options.theme' => ['nullable', 'string', 'in:light,dark,brand'],
            'options.maxWidth' => ['nullable', 'string', 'in:sm,md,lg,xl,2xl'],
            'options.backgroundColor' => ['nullable', 'string', 'max:50'],
            'options.accentColor' => ['nullable', 'string', 'max:50'],
            'options.showPoweredBy' => ['boolean'],
            'options.compactLayout' => ['boolean'],
            'allowedOrigins' => ['nullable', 'array'],
            'allowedOrigins.*' => ['string', 'max:255', 'distinct', new AllowedOriginExpressionRule],
        ];
    }

    /** @param array<string,mixed> $data */
    private function translationMode(array $data): TranslationSyncMode
    {
        $mode = $data['translation_mode'] ?? TranslationSyncMode::Patch;

        return $mode instanceof TranslationSyncMode ? $mode : TranslationSyncMode::from(is_string($mode) ? $mode : 'patch');
    }

    /** @param array<string, mixed> $data */
    private function validateMutationKeys(array $data): void
    {
        if (array_diff(array_keys($data), array_filter(array_keys($this->mutationRules(false)), static fn (string $key): bool => ! str_contains($key, '.'))) !== []) {
            throw ValidationException::withMessages(['attributes' => ['Unknown or protected form attributes are not accepted.']]);
        }
    }

    /**
     * @param  array<string,mixed>  $data
     * @param  list<string>  $except
     * @return array<string,mixed>
     */
    private function attributes(array $data, array $except): array
    {
        $result = [];
        foreach (array_diff_key($data, array_flip($except)) as $key => $value) {
            $result[Str::snake($key)] = $value;
        }

        return $result;
    }

    /** @param array<string,array<string,mixed>> $rows */
    private function syncTranslations(Form $form, array $rows, TranslationSyncMode $mode): void
    {
        $definition = $form->translationDefinition();
        app(TranslationPayloadValidator::class)->validate($definition, $rows);
        $form->loadMissing('translations');
        foreach ($rows as $locale => $attributes) {
            if ($mode === TranslationSyncMode::Patch) {
                $existing = $form->translations->firstWhere('locale', $locale);
                if ($existing instanceof FormTranslation) {
                    $content = $attributes['content'] ?? [];
                    if (is_array($content)) {
                        $attributes['content'] = array_replace($existing->content ?? [], $content);
                    }
                }
            }
            $this->translationWriter->upsert($form, $definition, $locale, $attributes);
        }
        if ($mode === TranslationSyncMode::Replace) {
            $this->translationWriter->deleteExcept($form, $definition, array_keys($rows));
        }
        $form->unsetRelation('translations');
    }
}
