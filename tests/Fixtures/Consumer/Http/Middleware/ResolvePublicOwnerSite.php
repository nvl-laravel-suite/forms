<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Nvl\Ownership\Services\PublicOwnerSiteRunner;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;
use Symfony\Component\HttpFoundation\Response;

final class ResolvePublicOwnerSite
{
    public function __construct(private PublicOwnerSiteRunner $runner) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $this->runner->run($request->getSchemeAndHttpHost(), fn () => $next($request));
        } catch (OwnershipViolation) {
            abort(404);
        }
    }
}
