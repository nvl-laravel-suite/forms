<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Data\Display;

/**
 * Derived status display state for a form show page.
 */
final class FormShowStatusState
{
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
        public readonly string $variant
    ) {}

    /** @return array<string, mixed> Consumer-facing response fields. */
    public function toArray(): array
    {
        return [
            'current' => $this->current,
            'label' => $this->label,
            'variant' => $this->variant,
        ];
    }
}
