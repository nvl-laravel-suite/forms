<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Nvl\Forms\Models\Form;

/** The consumer's forms listing envelope, built from Laravel pagination. */
final class PaginatedCollection
{
    /** @param list<array<string, mixed>> $items */
    public function __construct(
        public readonly array $items,
        public readonly PaginationMeta $meta,
    ) {}

    /** @param LengthAwarePaginator<int, Form> $paginator */
    public static function fromPaginator(LengthAwarePaginator $paginator): self
    {
        return new self(
            items: array_values(array_map(
                static fn (Form $form): array => FormPayload::fromModel($form)->toArray(),
                $paginator->items(),
            )),
            meta: new PaginationMeta(
                currentPage: $paginator->currentPage(),
                lastPage: $paginator->lastPage(),
                perPage: $paginator->perPage(),
                total: $paginator->total(),
            ),
        );
    }

    /** @return array{items: list<array<string, mixed>>, meta: array<string, mixed>} */
    public function toArray(): array
    {
        return ['items' => $this->items, 'meta' => $this->meta->toArray()];
    }
}
