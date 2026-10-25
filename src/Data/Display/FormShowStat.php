<?php

declare(strict_types=1);

namespace Nvl\Forms\Data\Display;

use Nvl\Data\Traits\DataTransform;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

/**
 * Single metric tile payload for form show-page stats.
 */
#[MapOutputName(CamelCaseMapper::class)]
#[MapInputName(CamelCaseMapper::class)]
final class FormShowStat extends Data
{
    use DataTransform;

    public function __construct(
        /**
         * @var string Icon key used by the frontend icon map
         */
        public readonly string $icon,

        /**
         * @var string Stat label
         */
        public readonly string $label,

        /**
         * @var string Stat value
         */
        public readonly string $value,

        /**
         * @var string Stat helper description
         */
        public readonly string $description,
    ) {}
}
