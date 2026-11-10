<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Data;

use Nvl\Forms\Models\Form;

/**
 * FormSuggestion: normalized DTO powering autocomplete payloads.
 */
final class FormSuggestion
{
    /**
     * Create the form suggestion data transfer object.
     *
     * @param  string  $id  Form identifier
     * @param  string  $label  Primary label
     * @param  string|null  $sublabel  Secondary label
     */
    public function __construct(
        /**
         * Unique form identifier (UUID).
         *
         * @var string
         */
        public readonly string $id,
        /**
         * Primary suggestion label (form name).
         *
         * @var string
         */
        public readonly string $label,
        /**
         * Optional secondary label (handle or meta).
         *
         * @var string|null
         */
        public readonly ?string $sublabel = null
    ) {}

    /**
     * Build the DTO from a Form model instance.
     *
     * @param  Form  $form  Form model instance
     * @return self Form suggestion data
     */
    public static function fromModel(Form $form): self
    {
        $sublabel = null;
        if (! empty($form->handle)) {
            $sublabel = (string) trans('nvl-forms::forms/general.card.handle', ['handle' => $form->handle]);
        }

        return new self(id: (string) $form->id, label: $form->displayName(), sublabel: $sublabel);
    }

    /** @return array<string, mixed> Consumer-facing response fields. */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'sublabel' => $this->sublabel,
        ];
    }
}
