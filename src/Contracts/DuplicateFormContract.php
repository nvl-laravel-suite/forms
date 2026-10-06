<?php

declare(strict_types=1);

namespace Nvl\Forms\Contracts;

use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Nvl\Forms\Models\Form;
use Throwable;

/**
 * Defines the supported duplicate form workflow.
 *
 * @api
 */
interface DuplicateFormContract
{
    /**
     * Execute the form duplication within a database transaction.
     *
     * Replicates the original form, generates a unique handle, resets statistics
     * duplicates allowed origins, and records domain activities.
     *
     * @param  Form|string  $form  Form instance or identifier to duplicate
     * @param  string|null  $newName  Optional new name for the duplicated form
     * @param  Authenticatable|null  $actor  Actor performing the duplication
     * @return Form The duplicated form instance with allowedOrigins loaded
     *
     * @throws Exception When duplication fails
     * @throws Throwable When transaction fails
     */
    public function execute(Form|string $form, ?string $newName = null, ?Authenticatable $actor = null): Form;
}
