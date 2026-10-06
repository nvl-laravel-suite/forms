<?php

declare(strict_types=1);

namespace Nvl\Forms\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Nvl\Forms\Models\Form;

/**
 * Defines the supported show form workflow.
 *
 * @api
 */
interface ShowFormContract
{
    /**
     * Resolve the form for display and optionally record a view event.
     *
     * @param  Form|string  $form  Form instance or identifier
     * @param  bool  $recordView  Whether the current request should be counted as a view
     * @param  string|null  $origin  Origin header or referrer value
     * @param  string|null  $ipAddress  Visitor IP address
     * @param  string|null  $userAgent  Visitor user agent
     * @param  string|null  $sessionId  Session identifier when available
     * @param  Authenticatable|null  $actor  Actor initiating the view
     * @return Form Form with the required relationships eagerly loaded
     */
    public function execute(
        Form|string $form,
        bool $recordView = true,
        ?string $origin = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $sessionId = null,
        ?Authenticatable $actor = null,
    ): Form;
}
