<?php

declare(strict_types=1);

namespace Nvl\Forms\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Nvl\Support\Auth\CanonicalActorResolver;
use Nvl\Support\Ownership\Services\OwnerSiteContext;

/**
 * Authorizes management through the explicitly supplied host principal.
 *
 * @internal
 */
final class FormManagement
{
    public static function authorize(?Authenticatable $actor): Authenticatable
    {
        if ($actor === null || OwnerSiteContext::current() !== null) {
            throw new AuthorizationException('Forms management requires an authorized actor.');
        }
        $actor = app(CanonicalActorResolver::class)->resolve($actor);
        $gate = config('nvl-forms.authorization.gate');
        if (! is_string($gate) || $gate === '' || Gate::forUser($actor)->denies($gate)) {
            throw new AuthorizationException('Forms management requires an authorized actor.');
        }

        return $actor;
    }
}
