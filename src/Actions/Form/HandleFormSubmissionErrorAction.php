<?php

declare(strict_types=1);

namespace Nvl\Forms\Actions\Form;

use Exception;
use Illuminate\Contracts\Foundation\Application;
use Nvl\Support\Contracts\RespondableException;
use Nvl\Support\Http\PackageExceptionPayload;

/**
 * Handles form submission error messages and formatting.
 * This action centralizes the complex error message logic
 * for form submission failures.
 *
 * @internal
 */
final readonly class HandleFormSubmissionErrorAction
{
    public function __construct(private Application $application, private PackageExceptionPayload $payload) {}

    /**
     * Execute error message handling.
     *
     * @param  Exception  $exception  Original exception
     * @return string Appropriate error message
     */
    public function execute(Exception $exception): string
    {
        if ($exception instanceof RespondableException) {
            return $this->payload->for($exception)['message'];
        }

        return match (true) {
            str_contains($exception->getMessage(), 'not allowed from this host') => (string) trans('nvl-forms::forms/messages.error.host_not_allowed'),
            str_contains($exception->getMessage(), 'Required fields missing') => $exception->getMessage(),
            str_contains($exception->getMessage(), 'origin required') => (string) trans('nvl-forms::forms/messages.error.origin_required'),
            default => $this->application->environment('local')
                ? $exception->getMessage()
                : (string) trans('nvl-forms::forms/messages.error.submission_failed'),
        };
    }
}
