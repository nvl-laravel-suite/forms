<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Data\Display;

/**
 * Badge payload used by forms show-page display states.
 */
final class FormShowBadge
{
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

    /** @return array<string, mixed> Consumer-facing response fields. */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'variant' => $this->variant,
        ];
    }
}
