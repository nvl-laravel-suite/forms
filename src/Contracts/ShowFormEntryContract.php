<?php

declare(strict_types=1);

namespace Nvl\Forms\Contracts;

use Nvl\Forms\Models\FormEntry;

/**
 * Defines the supported show form entry workflow.
 *
 * @api
 */
interface ShowFormEntryContract
{
    /**
     * Execute the form entry retrieval.
     *
     * @param  FormEntry|string  $formEntry  Form entry instance or identifier
     * @return FormEntry Form entry with loaded relationships
     */
    public function execute(FormEntry|string $formEntry): FormEntry;
}
