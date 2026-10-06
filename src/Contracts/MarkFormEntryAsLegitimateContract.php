<?php

declare(strict_types=1);

namespace Nvl\Forms\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Nvl\Forms\Models\FormEntry;
use Throwable;

/**
 * Defines the supported mark form entry as legitimate workflow.
 *
 * @api
 */
interface MarkFormEntryAsLegitimateContract
{
    /**
     * Mark the given entry as legitimate.
     *
     * @param  FormEntry|string  $entry  Entry model or identifier
     * @return FormEntry Updated entry model
     *
     * @throws Throwable
     */
    public function execute(FormEntry|string $entry, ?Authenticatable $actor = null): FormEntry;
}
