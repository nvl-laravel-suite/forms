<?php

declare(strict_types=1);

namespace Nvl\Forms\Data\Display;

use Nvl\Data\Traits\DataTransform;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

/**
 * Derived status display state for a form show page.
 */
#[MapOutputName(CamelCaseMapper::class)]
#[MapInputName(CamelCaseMapper::class)]
final class FormShowStatusState extends Data
{
    use DataTransform;

    public function __construct(
        /**
         * @var string|null Current status key
         */
        public readonly ?string $current,

        /**
         * @var string Translated status label
         */
        public readonly string $label,

        /**
         * @var string Badge variant token
         */
        public readonly string $variant,
    ) {}
}
