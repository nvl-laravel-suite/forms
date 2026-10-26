<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Data\Display;

use Nvl\Forms\Tests\Fixtures\Consumer\DataTransform;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

/**
 * Derived public/link payload for a form show page.
 */
#[MapOutputName(CamelCaseMapper::class)]
#[MapInputName(CamelCaseMapper::class)]
final class FormShowLinkState extends Data
{
    use DataTransform;

    public function __construct(
        /**
         * @var string Public form URL
         */
        public readonly string $viewUrl,
        /**
         * @var string Iframe embed snippet
         */
        public readonly string $embedCode
    ) {}
}
