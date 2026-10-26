<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Data\Display;

use Nvl\Forms\Tests\Fixtures\Consumer\DataTransform;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

/**
 * Derived security and availability state for a form show page.
 */
#[MapOutputName(CamelCaseMapper::class)]
#[MapInputName(CamelCaseMapper::class)]
final class FormShowSecurityState extends Data
{
    use DataTransform;

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
}
