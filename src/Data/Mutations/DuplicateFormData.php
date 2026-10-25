<?php

declare(strict_types=1);

namespace Nvl\Forms\Data\Mutations;

use Nvl\Data\Traits\DataTransform;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

#[MapInputName(CamelCaseMapper::class)]
#[MapOutputName(CamelCaseMapper::class)]
final class DuplicateFormData extends Data
{
    use DataTransform;

    public function __construct(
        public readonly ?string $name = null,

    ) {}

    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],

        ];
    }
}
