<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Data\Mutations;

use Illuminate\Support\Facades\Validator;
use Nvl\Forms\Rules\BoundedSubmissionPayload;
use Nvl\Forms\Tests\Fixtures\Consumer\TranslatedValidation;

/**
 * Consumer validation rules for public form submission payloads.
 *
 * @api
 */
class SubmitFormPayload
{
    use TranslatedValidation;

    /**
     * Validation rules for inbound public submissions.
     *
     * @return array<string, array<int, mixed>|string>
     */
    public static function rules(): array
    {
        return ['subject' => ['nullable', 'string', 'max:255'], 'firstName' => ['nullable', 'string', 'max:100'], 'lastName' => ['nullable', 'string', 'max:100'], 'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:50'], 'address' => ['nullable', 'string', 'max:500'], 'body' => ['nullable', 'string', 'max:50000'], 'submissionData' => ['nullable', 'array', new BoundedSubmissionPayload], 'submissionData.*' => ['nullable']];
    }

    /**
     * Custom validation messages sourced from entries translations.
     *
     * @return array<string, mixed>
     */
    public static function messages(): array
    {
        return self::translatedMessages('nvl-forms::entries');
    }

    /**
     * Attribute name mappings sourced from entries translations.
     *
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return self::translatedAttributes('nvl-forms::entries');
    }

    /**
     * Validate the public envelope and discard transport metadata owned by the request adapter.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function validatedInput(array $payload): array
    {
        return Validator::make($payload, self::rules(), self::messages(), self::attributes())->validate();
    }
}
