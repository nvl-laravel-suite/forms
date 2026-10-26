<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Data;

use Nvl\Forms\Tests\Fixtures\Consumer\DataTransform;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;
use Spatie\LaravelData\Optional;

/**
 * DTO for lightweight suggestion lookups while typing form names.
 */
#[MapOutputName(CamelCaseMapper::class)]
#[MapInputName(CamelCaseMapper::class)]
class FormSuggestions extends Data
{
    use DataTransform;

    /**
     * Create the form suggestions query data transfer object.
     *
     * @param  string  $q  Search query
     * @param  int|Optional  $limit  Result limit
     */
    public function __construct(
        /**
         * @var string
         */
        public readonly string $q,
        /**
         * @var int|Optional
         */
        public readonly int|Optional $limit = 10
    ) {}

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
