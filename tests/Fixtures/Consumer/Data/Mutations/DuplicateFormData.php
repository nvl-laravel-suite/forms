<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Data\Mutations;

/** Validation rules for an optional consumer-supplied duplicate name. */
final class DuplicateFormData
{
    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return ['name' => ['sometimes', 'nullable', 'string', 'max:255']];
    }
}
