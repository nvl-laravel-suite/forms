<?php

declare(strict_types=1);

namespace Nvl\Forms\Data;

use Nvl\Support\Ownership\ValueObjects\OwnerReference;

/**
 * Immutable public request facts delivered only after canonical re-admission at commit.
 *
 * @api
 */
final readonly class OwnershipFormSubmissionCallbackContext
{
    public function __construct(
        public OwnerReference $owner,
        public string $site,
        public string $canonicalOrigin,
        public ?string $origin,
        public string $locale,
        public ?string $correlationId,
    ) {}
}
