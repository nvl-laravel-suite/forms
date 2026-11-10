<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Data\Mutations;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Nvl\Forms\Enums\CorsPolicy;
use Nvl\Forms\Enums\FormStatus;
use Nvl\Forms\Enums\FormType;
use Nvl\Forms\Enums\Resolvement;
use Nvl\Forms\Rules\AllowedOriginExpressionRule;
use Nvl\Forms\Tests\Fixtures\Consumer\TranslatedValidation;
use Nvl\Translatable\Enums\TranslationSyncMode;
use Nvl\Translatable\Rules\SupportedLocaleMapRule;

/**
 * Consumer validation rules for form create and update operations.
 *
 * @api
 */
class MutateFormPayload
{
    use TranslatedValidation;

    /**
     * Validate and create a transport-neutral create mutation.
     *
     * @param  array<string, mixed>  $payload  Untrusted mutation input
     * @return array<string, mixed> Validated mutation fields
     */
    public static function validateForCreate(array $payload): array
    {
        $rules = self::rulesForCreate();
        Validator::make($payload, $rules, self::messages(), self::attributes())->validate();

        return array_intersect_key($payload, $rules);
    }

    /**
     * Validate and create a transport-neutral update mutation.
     *
     * @param  array<string, mixed>  $payload  Untrusted mutation input
     * @param  string  $formId  Server-resolved form identifier
     * @return array<string, mixed> Validated mutation fields
     */
    public static function validateForUpdate(array $payload, string $formId): array
    {
        $rules = self::rulesForUpdate($formId);
        Validator::make($payload, $rules, self::messages(), self::attributes())->validate();

        $validated = array_intersect_key($payload, $rules);
        $validated['expected_revision'] = (int) $validated['expected_revision'];

        return $validated;
    }

    /**
     * Return validation rules for a create mutation.
     *
     * @return array<string, array<int, mixed>> Validation rules
     */
    public static function rulesForCreate(): array
    {
        return self::rulesFor(false, null);
    }

    /**
     * Return validation rules for an update mutation.
     *
     * @param  string  $formId  Server-resolved form identifier
     * @return array<string, array<int, mixed>> Validation rules
     */
    public static function rulesForUpdate(string $formId): array
    {
        return self::rulesFor(true, $formId);
    }

    /**
     * Build mutation validation rules from explicit operation context.
     *
     * @return array<string, array<int, mixed>> Validation rules
     */
    private static function rulesFor(bool $updating, ?string $formId): array
    {
        $presenceRule = $updating ? 'sometimes' : 'required';

        return ['handle' => ['nullable', 'string', 'max:255'], 'translations' => [$presenceRule, 'array', new SupportedLocaleMapRule], 'translations.*' => ['nullable', 'array'], 'translations.*.name' => ['nullable', 'string', 'max:255'], 'translations.*.description' => ['nullable', 'string', 'max:2000'], 'translations.*.submitButtonLabel' => ['nullable', 'string', 'max:100'], 'translations.*.successTitle' => ['nullable', 'string', 'max:255'], 'translations.*.successMessage' => ['nullable', 'string', 'max:5000'], 'translation_mode' => ['sometimes', Rule::enum(TranslationSyncMode::class)], 'expected_revision' => [$updating ? 'required' : 'prohibited', 'integer', 'min:1'], 'resolvement' => [$presenceRule, Rule::enum(Resolvement::class)], 'type' => [$presenceRule, Rule::enum(FormType::class)], 'status' => ['sometimes', Rule::enum(FormStatus::class)], 'restrictPublicAccess' => ['boolean'], 'allowMultipleRegistrations' => ['boolean'], 'dateRestricted' => ['boolean'], 'availableFrom' => ['nullable', 'date'], 'availableUntil' => ['nullable', 'date', 'after_or_equal:availableFrom'], 'enableHoneypot' => ['boolean'], 'enableRateLimiting' => ['boolean'], 'rateLimitPerHour' => ['nullable', 'integer', 'min:1'], 'requireCsrf' => ['boolean'], 'corsSettings' => ['nullable', 'array:policy,allowCredentials,allowWildcards,maxAge,allowedMethods,allowedHeaders'], 'corsSettings.policy' => [Rule::enum(CorsPolicy::class)], 'corsSettings.allowCredentials' => ['boolean'], 'corsSettings.allowWildcards' => ['boolean'], 'corsSettings.maxAge' => ['integer', 'min:0', 'max:86400'], 'corsSettings.allowedMethods' => ['array', 'min:1'], 'corsSettings.allowedMethods.*' => ['string', 'distinct', Rule::in(['GET', 'POST', 'OPTIONS'])], 'corsSettings.allowedHeaders' => ['array', 'min:1'], 'corsSettings.allowedHeaders.*' => ['string', 'distinct', 'regex:/^(?:\*|[A-Za-z0-9!#$%&\\\'*+.^_`|~-]+)$/'], 'options' => ['nullable', 'array'], 'options.showHeader' => ['boolean'], 'options.showLogo' => ['boolean'], 'options.submitButtonLabel' => ['nullable', 'string', 'max:100'], 'options.successRedirectUrl' => ['nullable', 'string', 'url', 'max:500'], 'options.theme' => ['nullable', 'string', 'in:light,dark,brand'], 'options.maxWidth' => ['nullable', 'string', 'in:sm,md,lg,xl,2xl'], 'options.backgroundColor' => ['nullable', 'string', 'max:50'], 'options.accentColor' => ['nullable', 'string', 'max:50'], 'options.showPoweredBy' => ['boolean'], 'options.compactLayout' => ['boolean'], 'allowedOrigins' => ['nullable', 'array'], 'allowedOrigins.*' => ['string', 'max:255', 'distinct', new AllowedOriginExpressionRule]];
    }

    /**
     * Custom validation messages sourced from module translations.
     *
     * @return array<string, mixed>
     */
    public static function messages(): array
    {
        return self::translatedMessages('nvl-forms::forms');
    }

    /**
     * Attribute name mappings sourced from module translations.
     *
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return self::translatedAttributes('nvl-forms::forms');
    }
}
