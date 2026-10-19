<?php

declare(strict_types=1);

namespace Nvl\Forms\Models\Concerns;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Nvl\Support\Ownership\Contracts\OwnershipBoundary as TenantBoundary;
use Nvl\Support\Ownership\Contracts\OwnershipWriter;
use Nvl\Support\Ownership\Services\OwnershipResourceRegistry;
use Nvl\Support\Ownership\Traits\HasOwnership;
use Nvl\Support\Tenancy\Enums\TenantResourceKind;
use Nvl\Support\Tenancy\Exceptions\TenantBoundaryViolation;
use Nvl\Support\Tenancy\Services\TenantResourceRegistry;

/** Applies Forms's mandatory tenant predicate and immutable ownership stamp. */
trait GuardsTenantOwnership
{
    use HasOwnership { save as private saveOwned; }

    /** Persist fixed children through the same native source assignment as roots.
     * @param  array<string,mixed>  $options
     */
    public function save(array $options = []): bool
    {
        $resource = app(OwnershipResourceRegistry::class)->get(self::TENANT_RESOURCE);
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

    /** Register the package-owned query and mutation boundary. */
    protected static function bootGuardsTenantOwnership(): void
    {
        static::creating(static function (Model $model): void {
            $container = Container::getInstance();
            if ($container->make('config')->get('nvl-tenancy.enabled') !== true) {
                return;
            }
            $definition = $container->make(TenantResourceRegistry::class)->get(self::TENANT_RESOURCE);
            $boundary = $container->make(TenantBoundary::class);

            if ($definition->kind === TenantResourceKind::Root) {
                $model->forceFill($boundary->attributes(self::TENANT_RESOURCE));

                return;
            }

            if ($definition->kind !== TenantResourceKind::Inherited || $definition->parentRelation === null) {
                return;
            }

            $relation = $model->{$definition->parentRelation}();
            if (! $relation instanceof Relation) {
                throw new TenantBoundaryViolation('Forms child ownership requires a canonical relation.');
            }
            $parent = $relation->getResults();
            if (! $parent instanceof Model) {
                throw new TenantBoundaryViolation('Forms child ownership requires a canonical parent.');
            }

            $parentDefinition = $container->make(TenantResourceRegistry::class)->forModel($parent);
            $boundary->assertRecord($parent, $parentDefinition->key);
            $tenantId = $parent->getRawOriginal('tenant_id') ?? $parent->getAttribute('tenant_id');
            if (! is_string($tenantId)) {
                throw new TenantBoundaryViolation('Forms child ownership requires a tenant parent.');
            }

            $model->forceFill(['tenant_id' => $tenantId]);
        });

        static::updating(static function (Model $model): void {
            if ($model->isDirty('tenant_id')) {
                throw new TenantBoundaryViolation('Persisted Forms ownership is immutable.');
            }

            Container::getInstance()->make(TenantBoundary::class)->assertRecord($model, self::TENANT_RESOURCE);
        });
    }
}
