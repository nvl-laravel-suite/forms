<?php

declare(strict_types=1);

namespace Nvl\Forms\Contracts;

use Nvl\Forms\Data\Mutations\SubmitFormPayload;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Results\FormSubmissionResult;
use Nvl\Forms\Support\FormSubmissionContext;
use Throwable;

/**
 * Defines the supported handle public form submission workflow.
 *
 * @api
 */
interface HandlePublicFormSubmissionContract
{
    /**
     * Execute the public submission flow.
     *
     * @param  Form|string  $formIdentifier  Form model, UUID, or handle
     * @param  SubmitFormPayload  $data  Validated submission payload
     * @param  FormSubmissionContext  $context  Request-derived submission context
     * @param  bool  $enforceSubmissionProtection  Whether to enforce CSRF/public token checks
     * @return FormSubmissionResult Submission result payload
     *
     * @throws Throwable
     */
    public function execute(
        Form|string $formIdentifier,
        SubmitFormPayload $data,
        FormSubmissionContext $context,
        bool $enforceSubmissionProtection = true
    ): FormSubmissionResult;
}
