<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Data;

use Spatie\LaravelData\Data;

/**
 * One immutable Forms installation diagnostic.
 */
final class FormsDoctorCheckData extends Data
{
    public function __construct(public readonly string $key, public readonly string $severity, public readonly bool $passed, public readonly string $message) {}
}
