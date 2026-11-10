<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Data\Display;

/**
 * Root derived display-state payload for the forms show page.
 */
final class FormShowStates
{
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
         * @var list<FormShowStat> Stats cards payload
         */
        public readonly array $stats
    ) {}

    /** @return array<string, mixed> Consumer-facing response fields. */
    public function toArray(): array
    {
        return [
            'status' => $this->status->toArray(),
            'security' => $this->security->toArray(),
            'links' => $this->links->toArray(),
            'stats' => array_map(static fn (FormShowStat $stat): array => $stat->toArray(), $this->stats),
        ];
    }
}
