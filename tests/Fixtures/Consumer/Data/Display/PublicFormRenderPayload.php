<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Data\Display;

use Nvl\Forms\Enums\FormStatus;
use Nvl\Forms\Enums\FormType;
use Nvl\Forms\Tests\Fixtures\Consumer\Data\FormOptions;

/**
 * Public-safe localized form contract used by render consumers.
 */
final class PublicFormRenderPayload
{
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
        public readonly ?FormOptions $options
    ) {}

    /** @return array<string, mixed> Consumer-facing response fields. */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'handle' => $this->handle,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status->value,
            'type' => $this->type->value,
            'locale' => $this->locale,
            'content' => $this->content,
            'submitButtonLabel' => $this->submitButtonLabel,
            'successTitle' => $this->successTitle,
            'successMessage' => $this->successMessage,
            'restrictPublicAccess' => $this->restrictPublicAccess,
            'allowMultipleRegistrations' => $this->allowMultipleRegistrations,
            'options' => $this->options?->toArray(),
        ];
    }
}
