<?php

declare(strict_types=1);

namespace Nvl\Forms\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Nvl\Forms\Models\FormEntry;
use Throwable;

/**
 * Defines the supported mark form entry as spam workflow.
 *
 * @api
 */
interface MarkFormEntryAsSpamContract
{
    /**
     * Mark the given entry as spam.
     *
     * @param  FormEntry|string  $entry  Entry model or identifier
     * @param  string|null  $reason  Optional reason for spam classification
     * @return FormEntry Updated entry model
     *
     * @throws Throwable
     */
    public function execute(
        FormEntry|string $entry,
        ?string $reason = null,
        ?Authenticatable $actor = null,
    ): FormEntry;
}
