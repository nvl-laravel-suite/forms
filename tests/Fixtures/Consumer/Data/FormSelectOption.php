<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Data;

use Nvl\Forms\Tests\Fixtures\Consumer\TranslatedValidation;

/**
 * Consumer validation rules powering select/combobox options endpoints.
 */
class FormSelectOption
{
    use TranslatedValidation;

    /**
     * Validation rules for select option filters.
     *
     * @return array<string, array<int, mixed>|string>
     */
    public static function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:100'], 'activeOnly' => ['nullable', 'boolean'], 'publicOnly' => ['nullable', 'boolean'], 'withSubmissions' => ['nullable', 'boolean'], 'status' => ['nullable', 'string', 'in:draft,active,paused,archived']];
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
     * Localized validation messages for select queries.
     *
     * @return array<string, mixed>
     */
    public static function messages(): array
    {
        return self::translatedMessages('nvl-forms::forms');
    }
}
