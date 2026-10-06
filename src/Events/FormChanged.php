<?php

declare(strict_types=1);

namespace Nvl\Forms\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Nvl\Forms\Models\Form;
use Nvl\Support\Contracts\DomainEvent;

/**
 * Stable integration event for form-definition lifecycle changes.
 *
 * @api
 */
final class FormChanged implements DomainEvent
{
    /**
     * @param  array<string, bool|float|int|string|null>  $context
     */
    public function __construct(
        public readonly string $formId,
        public readonly string $operation,
        public readonly string|int|null $actorId = null,
        public readonly array $context = [],
        public readonly int $schemaVersion = 1,
    ) {}

    /**
     * Create an event without exposing an application-specific actor model.
     *
     * @param  array<string, bool|float|int|string|null>  $context
     */
    public static function for(
        Form $form,
        string $operation,
        ?Authenticatable $actor = null,
        array $context = [],
    ): self {
        $identifier = $actor?->getAuthIdentifier();
        $actorId = is_string($identifier) || is_int($identifier) ? $identifier : null;

        return new self($form->id, $operation, $actorId, $context);
    }

    /** Return the immutable event payload schema version. */
    public function schemaVersion(): int
    {
        return $this->schemaVersion;
    }
}
