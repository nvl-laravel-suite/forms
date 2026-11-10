<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Data\Display;

/**
 * Derived public/link payload for a form show page.
 */
final class FormShowLinkState
{
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

    /** @return array<string, mixed> Consumer-facing response fields. */
    public function toArray(): array
    {
        return [
            'viewUrl' => $this->viewUrl,
            'embedCode' => $this->embedCode,
        ];
    }
}
