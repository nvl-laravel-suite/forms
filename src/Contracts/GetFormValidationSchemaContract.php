<?php

declare(strict_types=1);

namespace Nvl\Forms\Contracts;

use Nvl\Forms\Data\Display\PublicFormSchemaPayload;
use Nvl\Forms\Models\Form;

/**
 * Defines the supported get form validation schema workflow.
 *
 * @api
 */
interface GetFormValidationSchemaContract
{
    /**
     * Execute the validation schema generation.
     *
     * @param  string  $formIdentifier  Form ID or handle
     * @return PublicFormSchemaPayload Public validation schema
     */
    public function execute(string $formIdentifier): PublicFormSchemaPayload;
}
