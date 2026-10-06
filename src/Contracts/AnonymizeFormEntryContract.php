<?php

declare(strict_types=1);

namespace Nvl\Forms\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Nvl\Forms\Models\FormEntry;

/**
 * Defines the supported anonymize form entry workflow.
 *
 * @api
 */
interface AnonymizeFormEntryContract
{
    /**
     * Execute the anonymize form entry workflow.
     */
    public function execute(
        FormEntry|string $entry,
        ?Authenticatable $actor = null,
    ): FormEntry;
}
