<?php

declare(strict_types=1);

namespace Nvl\Forms\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Nvl\Support\Ownership\Contracts\OwnershipWriter;
use Nvl\Support\Ownership\Services\OwnershipResourceRegistry;
use Nvl\Support\Ownership\Traits\HasOwnership;

/** Assigns inherited children through the native ownership writer. */
trait HasFormOwnership
{
    use HasOwnership { save as private saveOwned; }

    /** Persist fixed children through the same native source assignment as roots.
     * @param  array<string,mixed>  $options
     */
    public function save(array $options = []): bool
    {
        $resource = app(OwnershipResourceRegistry::class)->get(self::OWNERSHIP_RESOURCE);
        if (! $this->exists && $resource->ownership->strategy === 'inherited' && ! array_key_exists('owner_scope_key', $this->getAttributes())) {
            $saved = false;
            app(OwnershipWriter::class)->create(static::class, function (array $attributes) use ($options, &$saved): Model {
                $this->forceFill($attributes);
                $saved = $this->saveOwned($options);

                return $this;
            });

            return $saved;
        }

        return $this->saveOwned($options);
    }
}
