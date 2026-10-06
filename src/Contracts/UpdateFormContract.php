<?php

declare(strict_types=1);

namespace Nvl\Forms\Contracts;

use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Nvl\Forms\Data\Mutations\MutateFormPayload;
use Nvl\Forms\Models\Form;
use Throwable;

/**
 * Defines the supported update form workflow.
 *
 * @api
 */
interface UpdateFormContract
{
    /**
     * Execute the form update within a database transaction.
     *
     * Validates handle uniqueness if changed, updates form attributes,
     * syncs allowed origins, and returns the refreshed model.
     *
     * @param  Form|string  $form  Form instance or identifier
     * @param  MutateFormPayload  $data  Updated form mutation data
     * @param  Authenticatable|null  $actor  Authenticated actor performing the update
     * @return Form Updated form instance with allowedOrigins loaded
     *
     * @throws Exception When handle is not unique
     * @throws Throwable When transaction fails
     */
    public function execute(Form|string $form, MutateFormPayload $data, ?Authenticatable $actor = null): Form;
}
