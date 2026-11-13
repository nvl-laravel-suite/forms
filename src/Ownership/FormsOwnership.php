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

    /**
     * Return physical ownership columns required on partial SELECT queries when ownership is active.
     *
     * @return list<string>
     */
    public static function columns(string $resource): array
    {
        if (config('nvl-ownership.enabled') !== true || ! self::declared()) {
            return [];
        }

        $definition = app(OwnershipResourceRegistry::class)->all()[$resource] ?? null;
        if ($definition === null || $definition->ownership->strategy === 'unscoped') {
            return [];
        }

        $columns = ['owner_scope_key'];
        if ($definition->ownership->strategy === 'column' && is_string($definition->ownership->foreignKey)) {
            $columns[] = $definition->ownership->foreignKey;
        } elseif ($definition->ownership->strategy === 'captured') {
            $columns[] = 'owner_reference';
        }

        return $columns;
    }

    /** Return the eager-load column specification for `form` relations. */
    public static function formRelationSelect(): string
    {
        return implode(',', ['id', 'handle', ...self::columns('forms.forms')]);
    }

    private function __construct() {}
}
