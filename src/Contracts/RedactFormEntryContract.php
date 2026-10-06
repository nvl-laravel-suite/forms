<?php

declare(strict_types=1);

namespace Nvl\Forms\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Nvl\Forms\Models\FormEntry;

/**
 * Defines the supported redact form entry workflow.
 *
 * @api
 */
interface RedactFormEntryContract
{
    /**
     * @param  list<string>  $fields
     */
    public function execute(
        FormEntry|string $entry,
        array $fields,
        ?Authenticatable $actor = null,
    ): FormEntry;
}
