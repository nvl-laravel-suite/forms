<?php

declare(strict_types=1);

namespace Nvl\Forms\Ownership;

use Nvl\Support\Ownership\Services\OwnershipResourceRegistry;

/**
 * Resolves canonical metadata without retaining request admission.
 *
 * @internal
 */
final class FormsOwnership
{
    /** Determine whether this package graph declares native primary ownership. */
    public static function declared(): bool
    {
        return (app(OwnershipResourceRegistry::class)->all()['forms.forms'] ?? null)?->ownership->strategy !== null && app(OwnershipResourceRegistry::class)->get('forms.forms')->ownership->strategy !== 'unscoped';
    }

    private function __construct() {}
}
