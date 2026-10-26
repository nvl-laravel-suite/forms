<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Data\Display;

use Nvl\Forms\Tests\Fixtures\Consumer\DataTransform;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

/**
 * Badge payload used by forms show-page display states.
 */
#[MapOutputName(CamelCaseMapper::class)]
#[MapInputName(CamelCaseMapper::class)]
final class FormShowBadge extends Data
{
    use DataTransform;

    public function __construct(
        /**
         * @var string Display label
         */
        public readonly string $label,
        /**
         * @var string Badge variant token
         */
        public readonly string $variant
    ) {}
}
