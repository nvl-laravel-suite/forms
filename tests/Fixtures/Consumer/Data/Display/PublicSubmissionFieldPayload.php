<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Data\Display;

use Nvl\Forms\Tests\Fixtures\Consumer\DataTransform;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

/**
 * JSON-safe validation metadata for one public submission field.
 *
 * @api
 */
#[MapInputName(CamelCaseMapper::class)]
#[MapOutputName(CamelCaseMapper::class)]
final class PublicSubmissionFieldPayload extends Data
{
    use DataTransform;

    /**
     * @param  string  $key  Submission field key
     * @param  bool  $required  Whether the field is required
     * @param  list<string>  $rules  Normalized validation rules
     */
    public function __construct(
        public readonly string $key,
        public readonly bool $required,
        /** @var list<string> */
        public readonly array $rules
    ) {}
}
