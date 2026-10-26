<?php

declare(strict_types=1);

namespace Nvl\Forms\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Nvl\Forms\Policies\FormPolicy;

/**
 * Authorizes management through the explicitly supplied host principal.
 *
 * @internal
 */
final class FormManagement
{
    public static function authorize(?Authenticatable $actor): void
    {
        if (! app(FormPolicy::class)->viewAny($actor)) {
            throw new AuthorizationException('Forms management requires an authorized actor.');
        }
    }
}
