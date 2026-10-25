<?php

declare(strict_types=1);

namespace Nvl\Forms\Data\Display;

use Nvl\Data\Traits\DataTransform;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

/**
 * Public JSON-safe validation schema for the generic submission envelope.
 *
 * @api
 */
#[MapInputName(CamelCaseMapper::class)]
#[MapOutputName(CamelCaseMapper::class)]
final class PublicFormSchemaPayload extends Data
{
    use DataTransform;

    /**
     * @param  string  $formId  Form identifier
     * @param  string  $name  Localized form name
     * @param  array<int, PublicSubmissionFieldPayload>  $fields  Top-level field definitions
     * @param  array<string, list<string>>  $validationRules  JSON-safe validation rules
     * @param  array<string, mixed>  $messages  Translated validation messages
     * @param  array<string, string>  $attributes  Translated validation attributes
     */
    public function __construct(
        public readonly string $formId,

        public readonly string $name,

        /** @var array<int, PublicSubmissionFieldPayload> */
        #[DataCollectionOf(PublicSubmissionFieldPayload::class)]
        public readonly array $fields,

        /** @var array<string, list<string>> */
        public readonly array $validationRules,

        /** @var array<string, mixed> */
        public readonly array $messages,

        /** @var array<string, string> */
        public readonly array $attributes,
    ) {}
}
