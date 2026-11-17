<?php

declare(strict_types=1);

namespace Nvl\Forms\Support;

use Illuminate\Validation\ValidationException;

/**
 * Rejects entry text a database would refuse or silently truncate.
 *
 * Shared by public submission and management entry updates so both store the same text.
 *
 * @internal
 */
final class SubmissionText
{
    /**
     * @param  array<array-key, mixed>  $fields
     *
     * @throws ValidationException When a text field is not valid UTF-8 or contains a NUL byte
     */
    public static function assertStorable(array $fields): void
    {
        foreach ($fields as $value) {
            if (is_string($value) && (! mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0"))) {
                throw ValidationException::withMessages(['fields' => 'Submission text must be valid UTF-8 without NUL bytes.']);
            }
        }
    }

    private function __construct() {}
}
