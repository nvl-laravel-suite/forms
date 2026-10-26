<?php

declare(strict_types=1);

namespace Nvl\Forms\Contracts;

use Nvl\Forms\Models\Form;

/**
 * Contract for custom form submission handlers.
 *
 * @api
 */
interface CustomFormHandler
{
    /**
     * Handle a custom form submission.
     *
     * @param  Form  $form  The form model
     * @param  array<string, mixed>  $data  The validated submission payload
     * @param  array<string,mixed>  $context  The original request
     * @return array{entry_id?: string, meta?: array<string, mixed>} A response payload
     */
    public function handle(Form $form, array $data, array $context): array;
}
