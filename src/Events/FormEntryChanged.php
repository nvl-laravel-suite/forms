<?php

declare(strict_types=1);

namespace Nvl\Forms\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormEntry;
use Nvl\Support\Contracts\DomainEvent;

/**
 * Stable integration event for form-entry lifecycle and privacy operations.
 *
 * @api
 */
final class FormEntryChanged implements DomainEvent
{
    /**
     * @param  array<string, bool|float|int|string|null>  $context
     */
    public function __construct(
        public readonly string $formId,
        public readonly string $entryId,
        public readonly string $operation,
        public readonly string|int|null $actorId = null,
        public readonly array $context = [],
        public readonly int $schemaVersion = 1,
    ) {}

    /**
     * Create a sanitized event without serializing the entry's personal data.
     *
     * @param  array<string, bool|float|int|string|null>  $context
     */
    public static function for(
        Form $form,
        FormEntry|string $entry,
        string $operation,
        ?Authenticatable $actor = null,
        array $context = [],
    ): self {
        $identifier = $actor?->getAuthIdentifier();
        $actorId = is_string($identifier) || is_int($identifier) ? $identifier : null;

        return new self(
            $form->id,
            $entry instanceof FormEntry ? $entry->id : $entry,
            $operation,
            $actorId,
            $context,
        );
    }

    /** Return the immutable event payload schema version. */
    public function schemaVersion(): int
    {
        return $this->schemaVersion;
    }
}
