<?php

declare(strict_types=1);

namespace Nvl\Forms\Data;

use Nvl\Data\Traits\DataTransform;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

/**
 * Typed select option returned by form select endpoints.
 *
 * @api
 */
#[MapOutputName(CamelCaseMapper::class)]
#[MapInputName(CamelCaseMapper::class)]
final class FormSelectOptionItem extends Data
{
    use DataTransform;

    /**
     * Create one form select option.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly ?string $sublabel,
    ) {}
}
