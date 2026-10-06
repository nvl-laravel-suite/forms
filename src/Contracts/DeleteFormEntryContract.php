<?php

declare(strict_types=1);

namespace Nvl\Forms\Contracts;

use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormEntry;
use Throwable;

/**
 * Defines the supported delete form entry workflow.
 *
 * @api
 */
interface DeleteFormEntryContract
{
    /**
     * Execute the form entry deletion.
     *
     * @param  FormEntry|string  $formEntry  Form entry instance or identifier
     * @param  Authenticatable|null  $actor  Actor performing the deletion
     * @return bool True if deletion was successful
     *
     * @throws Exception|Throwable If deletion fails or entry cannot be deleted
     */
    public function execute(FormEntry|string $formEntry, ?Authenticatable $actor = null): bool;
}
