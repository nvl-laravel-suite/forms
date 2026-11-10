<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Data;

use Nvl\Forms\Tests\Fixtures\Consumer\TranslatedValidation;

/**
 * Consumer validation rules for suggestion lookups.
 */
class FormSuggestions
{
    use TranslatedValidation;

    /**
     * Validation rules for suggestion queries.
     *
     * @return array<string, array<int, mixed>|string>
     */
    public static function rules(): array
    {
        return ['q' => ['required', 'string', 'min:2', 'max:50'], 'limit' => ['nullable', 'integer', 'min:1', 'max:50']];
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
     * Localized validation messages for suggestion requests.
     *
     * @return array<string, mixed>
     */
    public static function messages(): array
    {
        return self::translatedMessages('nvl-forms::forms');
    }
}
