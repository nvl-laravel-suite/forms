<?php

declare(strict_types=1);

namespace Nvl\Forms\Contracts;

use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Nvl\Forms\Models\Form;
use Throwable;

/**
 * Defines the supported delete form workflow.
 *
 * @api
 */
interface DeleteFormContract
{
    /**
     * Execute the form deletion.
     *
     * @param  Form|string  $form  Form instance or identifier
     * @param  Authenticatable|null  $actor  Authenticated actor performing the deletion
     * @return bool Deletion success
     *
     * @throws Exception|Throwable If form not found or has dependencies
     */
    public function execute(Form|string $form, ?Authenticatable $actor = null): bool;
}
