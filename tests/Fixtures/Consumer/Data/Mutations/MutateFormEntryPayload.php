<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Data\Mutations;

use Nvl\Forms\Definitions\Tables\FormsTables;
use Nvl\Forms\Tests\Fixtures\Consumer\TranslatedValidation;

/**
 * Consumer validation rules for form entry creation from submission pipelines.
 */
class MutateFormEntryPayload
{
    use TranslatedValidation;

    /**
     * Validation rules for form entry mutation payloads.
     *
     * @return array<string, array<int, mixed>> Validation rules
     */
    public static function rules(): array
    {
        return ['formId' => ['required', 'uuid', 'exists:'.FormsTables::get(FormsTables::Forms).',id'], 'subject' => ['nullable', 'string', 'max:255'], 'email' => ['nullable', 'email', 'max:255'], 'firstName' => ['nullable', 'string', 'max:100'], 'lastName' => ['nullable', 'string', 'max:100'], 'phone' => ['nullable', 'string', 'max:20'], 'address' => ['nullable', 'string'], 'body' => ['nullable', 'string'], 'submissionData' => ['nullable', 'array'], 'submittedFrom' => ['required', 'string', 'max:255'], 'ipAddress' => ['nullable', 'ip'], 'userAgent' => ['nullable', 'string', 'max:500'], 'sessionId' => ['nullable', 'string', 'max:100'], 'isSpam' => ['boolean'], 'spamScore' => ['nullable', 'integer', 'between:0,100'], 'securityFlags' => ['nullable', 'array']];
    }

    /**
     * Custom validation messages sourced from module translations.
     *
     * @return array<string, mixed>
     */
    public static function messages(): array
    {
        return self::translatedMessages('nvl-forms::entries');
    }

    /**
     * Attribute name mappings sourced from module translations.
     *
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return self::translatedAttributes('nvl-forms::entries');
    }
}
