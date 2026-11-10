<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Data;

use Nvl\Forms\Tests\Fixtures\Consumer\TranslatedValidation;

/**
 * Consumer validation rules used to filter forms for admin search APIs.
 */
class FormSearchFilter
{
    use TranslatedValidation;

    /**
     * Validation rules for the search payload.
     *
     * @return array<string, array<int, mixed>|string>
     */
    public static function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:100'], 'handle' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', 'string', 'in:draft,active,paused,archived'], 'restrictPublicAccess' => ['nullable', 'boolean'], 'hasSubmissions' => ['nullable', 'boolean'], 'recentlyUsed' => ['nullable', 'boolean'], 'createdAfter' => ['nullable', 'date'], 'createdBefore' => ['nullable', 'date'], 'limit' => ['nullable', 'integer', 'min:1', 'max:100'], 'with' => ['nullable', 'array'], 'with.*' => ['string', 'in:entries']];
    }

    /**
     * Attribute name mappings sourced from module translations.
     *
     * @return array<string, mixed>
     */
    public static function attributes(): array
    {
        return self::translatedAttributes('nvl-forms::forms');
    }

    /**
     * Localized validation messages for search rules.
     *
     * @return array<string, mixed>
     */
    public static function messages(): array
    {
        return self::translatedMessages('nvl-forms::forms');
    }
}
