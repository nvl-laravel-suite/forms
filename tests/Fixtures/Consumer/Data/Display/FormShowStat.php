<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Data\Display;

/**
 * Single metric tile payload for form show-page stats.
 */
final class FormShowStat
{
    public function __construct(
        /**
         * @var string Icon key used by the frontend icon map
         */
        public readonly string $icon,
        /**
         * @var string Stat label
         */
        public readonly string $label,
        /**
         * @var string Stat value
         */
        public readonly string $value,
        /**
         * @var string Stat helper description
         */
        public readonly string $description
    ) {}

    /** @return array<string, mixed> Consumer-facing response fields. */
    public function toArray(): array
    {
        return [
            'icon' => $this->icon,
            'label' => $this->label,
            'value' => $this->value,
            'description' => $this->description,
        ];
    }
}
