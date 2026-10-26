<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Services;

use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormEntry;
use Nvl\Forms\Models\FormSubmissionReceipt;
use Nvl\Forms\Support\FormErrorMapperRegistry;
use Nvl\Forms\Tests\Fixtures\Consumer\Presentation\HandleFormSubmissionErrorAction;
use Nvl\Support\Exceptions\BusinessException;

/**
 * Maps reusable public submission warning and business-error response data.
 */
final class PublicFormSubmissionResponseMapper
{
    /**
     * @param  FormErrorMapperRegistry  $errorMapperRegistry  Registry for form-specific business error mappers
     */
    public function __construct(private readonly FormErrorMapperRegistry $errorMapperRegistry, private readonly HandleFormSubmissionErrorAction $errors) {}

    /**
     * Resolve an optional warning message for degraded Forms bookkeeping.
     *
     * @param  FormSubmissionResult  $submissionResult  Submission result to inspect
     * @return string|null Warning message or null when bookkeeping succeeded
     */
    public function warning(FormEntry|FormSubmissionReceipt $submissionResult): ?string
    {
        if (! ($submissionResult instanceof FormSubmissionReceipt && $submissionResult->hasBookkeepingWarning())) {
            return null;
        }

        return (string) trans('nvl-forms::forms/messages.warning.submission_recording_delayed');
    }

    /**
     * Map a business exception to custom form errors, falling back to a top-level error.
     *
     * @param  Form  $form  Form that received the submission
     * @param  BusinessException  $exception  Exception to map
     * @return array<string, mixed> Mapped errors
     */
    public function businessErrors(Form $form, BusinessException $exception): array
    {
        $message = $this->errors->execute($exception);
        $mappedErrors = $this->errorMapperRegistry->map($form, $exception);
        if ($mappedErrors !== null) {
            return $mappedErrors;
        }

        return ['error' => $message];
    }
}
