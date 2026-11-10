<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Data;

/**
 * Describes stable length-aware pagination metadata for public NVL payloads.
 *
 * @internal
 */
final class PaginationMeta
{
    /**
     * Create pagination metadata.
     */
    public function __construct(
        public readonly int $currentPage,
        public readonly int $lastPage,
        public readonly int $perPage,
        public readonly int $total,
    ) {}

    /** @return array<string, mixed> Consumer-facing response fields. */
    public function toArray(): array
    {
        return [
            'currentPage' => $this->currentPage,
            'lastPage' => $this->lastPage,
            'per_page' => $this->perPage,
            'total' => $this->total,
        ];
    }
}
