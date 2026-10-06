<?php

declare(strict_types=1);

namespace Nvl\Forms\Exceptions;

use Exception;
use Nvl\Forms\Enums\FormResponseCode;
use Nvl\Support\Contracts\RespondableException;
use Nvl\Support\Exceptions\ExceptionResponse;
use Nvl\Support\Traits\InteractsWithPackageFailure;

/**
 * Failure handle for public Forms workflows.
 *
 * @api
 */
class FormException extends Exception implements RespondableException
{
    use InteractsWithPackageFailure;

    /** Resolve native Forms failure metadata. */
    protected function exceptionResponse(): ExceptionResponse
    {
        return match (true) {
            $this instanceof FormSubmissionRejectionException => new ExceptionResponse('forms', FormResponseCode::SubmissionRejected, $this->statusCode()),
            $this instanceof FormOwnershipException => new ExceptionResponse('forms', FormResponseCode::OwnershipDenied, 403),
            default => new ExceptionResponse('forms', FormResponseCode::OperationFailed),
        };
    }
}
