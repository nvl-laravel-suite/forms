<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Presentation;

use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Translation\Translator;
use Nvl\Forms\Exceptions\FormSubmissionRejectionException;
use Nvl\Support\Contracts\RespondableException;

/**
 * Handles form submission error messages and formatting.
 * This action centralizes the complex error message logic
 * for form submission failures.
 *
 * @internal
 */
final readonly class HandleFormSubmissionErrorAction
{
    public function __construct(private Application $application, private Translator $translator) {}

    /**
     * Execute error message handling.
     *
     * @param  Exception  $exception  Original exception
     * @return string Appropriate error message
     */
    public function execute(Exception $exception): string
    {
        if ($exception instanceof FormSubmissionRejectionException) {
            return (string) trans('nvl-forms::responsecode.submission_rejected');
        }
        if ($exception instanceof RespondableException) {
            $message = $this->translator->get($exception->translationKey() ?? 'nvl-core::responsecode.operation_failed', $exception->translationParameters());

            return is_string($message) ? $message : 'The operation could not be completed.';
        }

        return match (true) {
            str_contains($exception->getMessage(), 'not allowed from this host') => (string) trans('nvl-forms::forms/messages.error.host_not_allowed'),
            str_contains($exception->getMessage(), 'Required fields missing') => $exception->getMessage(),
            str_contains($exception->getMessage(), 'origin required') => (string) trans('nvl-forms::forms/messages.error.origin_required'),
            default => $this->application->environment('local') ? $exception->getMessage() : (string) trans('nvl-forms::forms/messages.error.submission_failed'),
        };
    }
}
