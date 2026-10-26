<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Data\Display;

use Nvl\Forms\Tests\Fixtures\Consumer\DataTransform;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

/**
 * Root derived display-state payload for the forms show page.
 */
#[MapOutputName(CamelCaseMapper::class)]
#[MapInputName(CamelCaseMapper::class)]
final class FormShowStates extends Data
{
    use DataTransform;

    public function __construct(
        /**
         * @var FormShowStatusState Resolved status state
         */
        public readonly FormShowStatusState $status,
        /**
         * @var FormShowSecurityState Resolved security/availability state
         */
        public readonly FormShowSecurityState $security,
        /**
         * @var FormShowLinkState Resolved public/embed links
         */
        public readonly FormShowLinkState $links,
        /**
         * @var DataCollection<int, FormShowStat> Stats cards payload
         */
        #[DataCollectionOf(FormShowStat::class)]
        public readonly DataCollection $stats
    ) {}
}
