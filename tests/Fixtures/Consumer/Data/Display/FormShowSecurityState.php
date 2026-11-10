<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Data\Display;

/**
 * Derived security and availability state for a form show page.
 */
final class FormShowSecurityState
{
    public function __construct(
        /**
         * @var string Yes label from shared translations
         */
        public readonly string $yesLabel,
        /**
         * @var string No label from shared translations
         */
        public readonly string $noLabel,
        /**
         * @var bool Whether public access is restricted
         */
        public readonly bool $isRestricted,
        /**
         * @var FormShowBadge Restrict public access badge
         */
        public readonly FormShowBadge $restrictPublicAccess,
        /**
         * @var FormShowBadge Allow multiple registrations badge
         */
        public readonly FormShowBadge $allowMultipleRegistrations,
        /**
         * @var FormShowBadge Honeypot enabled badge
         */
        public readonly FormShowBadge $enableHoneypot,
        /**
         * @var FormShowBadge CSRF required badge
         */
        public readonly FormShowBadge $requireCsrf,
        /**
         * @var FormShowBadge Rate limiting enabled badge
         */
        public readonly FormShowBadge $enableRateLimiting,
        /**
         * @var FormShowBadge Date restricted badge
         */
        public readonly FormShowBadge $dateRestricted,
        /**
         * @var FormShowBadge Current availability badge
         */
        public readonly FormShowBadge $availability,
        /**
         * @var string Resolved rate limit value display
         */
        public readonly string $rateLimitPerHourDisplay,
        /**
         * @var string Resolved resolvement label
         */
        public readonly string $resolvementLabel,
        /**
         * @var string Resolved form type label
         */
        public readonly string $typeLabel
    ) {}

    /** @return array<string, mixed> Consumer-facing response fields. */
    public function toArray(): array
    {
        return [
            'yesLabel' => $this->yesLabel,
            'noLabel' => $this->noLabel,
            'isRestricted' => $this->isRestricted,
            'restrictPublicAccess' => $this->restrictPublicAccess->toArray(),
            'allowMultipleRegistrations' => $this->allowMultipleRegistrations->toArray(),
            'enableHoneypot' => $this->enableHoneypot->toArray(),
            'requireCsrf' => $this->requireCsrf->toArray(),
            'enableRateLimiting' => $this->enableRateLimiting->toArray(),
            'dateRestricted' => $this->dateRestricted->toArray(),
            'availability' => $this->availability->toArray(),
            'rateLimitPerHourDisplay' => $this->rateLimitPerHourDisplay,
            'resolvementLabel' => $this->resolvementLabel,
            'typeLabel' => $this->typeLabel,
        ];
    }
}
