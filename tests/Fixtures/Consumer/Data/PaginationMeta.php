<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Data;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Describes stable length-aware pagination metadata for public NVL payloads.
 *
 * @internal
 */
#[TypeScript]
final class PaginationMeta extends Data
{
    /**
     * Create pagination metadata.
     */
    public function __construct(
        public readonly int $currentPage,
        public readonly int $lastPage,
        #[MapInputName('per_page')] #[MapOutputName('per_page')] public readonly int $perPage,
        public readonly int $total,
    ) {}
}
