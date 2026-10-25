<?php

declare(strict_types=1);

namespace Nvl\Forms\Data\Display;

use Nvl\Data\Traits\DataTransform;
use Nvl\Forms\Data\FormOptions;
use Nvl\Forms\Enums\FormStatus;
use Nvl\Forms\Enums\FormType;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

/**
 * Public-safe localized form contract used by render consumers.
 */
#[MapInputName(CamelCaseMapper::class)]
#[MapOutputName(CamelCaseMapper::class)]
final class PublicFormRenderPayload extends Data
{
    use DataTransform;

    /**
     * @param  string  $id  Form identifier
     * @param  string|null  $handle  Public form handle
     * @param  string  $name  Localized form name
     * @param  string|null  $description  Localized form description
     * @param  FormStatus  $status  Current form status
     * @param  FormType  $type  Public rendering mode
     * @param  string  $locale  Resolved content locale
     * @param  array<string, mixed>  $content  Localized form definition
     * @param  string|null  $submitButtonLabel  Localized submit button label
     * @param  string|null  $successTitle  Localized success title
     * @param  string|null  $successMessage  Localized success message
     * @param  bool  $restrictPublicAccess  Whether origin restrictions are enabled
     * @param  bool  $allowMultipleRegistrations  Whether repeat registrations are allowed
     * @param  FormOptions|null  $options  Public display and behavior options
     */
    public function __construct(
        public readonly string $id,

        public readonly ?string $handle,

        public readonly string $name,

        public readonly ?string $description,

        public readonly FormStatus $status,

        public readonly FormType $type,

        public readonly string $locale,

        /** @var array<string, mixed> */
        public readonly array $content,

        public readonly ?string $submitButtonLabel,

        public readonly ?string $successTitle,

        public readonly ?string $successMessage,

        public readonly bool $restrictPublicAccess,

        public readonly bool $allowMultipleRegistrations,

        public readonly ?FormOptions $options,
    ) {}
}
