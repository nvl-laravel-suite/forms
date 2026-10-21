<?php

declare(strict_types=1);

namespace Nvl\Forms\Ownership;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Schema\Blueprint;
use Nvl\Ownership\Contracts\OwnershipAdoptionAdapter;
use Nvl\Ownership\Services\OwnershipAdoptionStore;
use Nvl\Ownership\Services\OwnershipDatabaseConstraints;
use Nvl\Ownership\Services\OwnershipResourceBackfill;
use Nvl\Ownership\Services\OwnershipSchema;
use Nvl\Ownership\ValueObjects\OwnershipAdoptionPlan;
use Nvl\Ownership\ValueObjects\OwnershipBackfillResult;
use Nvl\Ownership\ValueObjects\OwnershipVerification;
use Nvl\Support\Ownership\Exceptions\OwnershipConfigurationInvalid;
use Nvl\Support\Ownership\Services\OwnershipResourceRegistry;

/**
 * Adopts exact reviewed Forms records without guessing primary owners from associations.
 *
 * @internal
 */
final readonly class FormsAdoptionAdapter implements OwnershipAdoptionAdapter
{
    /** Retain canonical services and the 6.x constraints argument; OwnershipSchema owns constraint installation. */
    public function __construct(
        private OwnershipResourceRegistry $resources,
        private OwnershipAdoptionStore $mappings,
        private OwnershipSchema $schema,
        OwnershipDatabaseConstraints $constraints,
    ) {
        unset($constraints);
    }

    /** @return list<string> */
    public function resources(): array
    {
        return ['forms.forms', 'forms.entries', 'forms.receipts', 'forms.origins', 'forms.rates', 'forms.analytics', 'forms.translations'];
    }

    /** Prepare additive partitions and replace only the package's known global receipt identity. */
    public function prepare(OwnershipAdoptionPlan $plan): void
    {
        foreach ($this->resources() as $key) {
            $model = new ($this->resources->get($key)->model);
            $schema = $model->getConnection()->getSchemaBuilder();
            foreach (['tenant_id', 'ownership_key'] as $column) {
                if ($schema->hasColumn($model->getTable(), $column)) {
                    $schema->table($model->getTable(), static fn (Blueprint $table) => ($column === 'tenant_id' ? $table->uuid($column) : $table->string($column, 191))->nullable()->change());
                }
            }
            $this->schema->prepare($key);
            $identities = $key === 'forms.forms' ? [['handle']] : [];
            foreach ($schema->getIndexes($model->getTable()) as $index) {
                $columns = $index['columns'];
                $withoutTenant = array_values(array_diff($columns, ['tenant_id']));
                if ($index['unique'] && in_array($withoutTenant, $identities, true)) {
                    $schema->table($model->getTable(), static fn (Blueprint $table) => $table->dropUnique($index['name']));
                }
            }
            foreach ($identities as $identity) {
                $this->unique($model, ['owner_scope_key', ...$identity], 'forms_owned_'.substr(hash('sha256', $model->getTable().implode(',', $identity)), 0, 24));
            }
        }
        $this->restoreNativeParents();
    }

    /** Restore the concrete native FK when a legacy adapter replaced it with a tenant composite.
     * @param  list<array{string,string}>  $extraEdges
     */
    private function restoreNativeParents(array $extraEdges = []): void
    {
        foreach ($this->resources() as $key) {
            $definition = $this->resources->get($key);
            if ($definition->ownership->strategy !== 'inherited' || count($definition->parents()) !== 1) {
                continue;
            }
            $model = new $definition->model;
            $relationName = $definition->ownership->relation ?? throw new OwnershipConfigurationInvalid('Inherited ownership requires a concrete parent relation.');
            $relation = $model->{$relationName}();
            if (! $relation instanceof BelongsTo) {
                throw new OwnershipConfigurationInvalid('Inherited ownership requires a canonical native parent relation.');
            }
            if ($relation instanceof MorphTo) {
                continue;
            }
            $parent = new ($this->resources->get($definition->parents()[0])->model);
            $this->restoreNativeForeign($model, $relation->getForeignKeyName(), $parent);
        }
        foreach ($extraEdges as [$resource, $column]) {
            $model = new ($this->resources->get($resource)->model);
            $this->restoreNativeForeign($model, $column, $model);
        }
    }

    /** Preserve existing keys and add only a missing exact native parent constraint. */
    private function restoreNativeForeign(Model $model, string $column, Model $parent): void
    {
        if ($model->getConnection() !== $parent->getConnection()) {
            throw new OwnershipConfigurationInvalid('Inherited package records require the same actual native source.');
        }
        $schema = $model->getConnection()->getSchemaBuilder();
        foreach ($schema->getForeignKeys($model->getTable()) as $foreign) {
            if ($foreign['columns'] === [$column]
                && $foreign['foreign_table'] === $parent->getConnection()->getTablePrefix().$parent->getTable()
                && $foreign['foreign_columns'] === [$parent->getKeyName()]) {
                return;
            }
        }
        $name = 'nvl_native_'.substr(hash('sha256', $model->getTable().$column), 0, 24);
        $schema->table($model->getTable(), static fn (Blueprint $table) => $table->foreign([$column], $name)->references([$parent->getKeyName()])->on($parent->getTable())->restrictOnDelete());
    }

    /** @param list<string> $columns */
    private function unique(Model $model, array $columns, string $name): void
    {
        $schema = $model->getConnection()->getSchemaBuilder();
        foreach ($schema->getIndexes($model->getTable()) as $index) {
            if ($index['unique'] && $index['columns'] === $columns) {
                return;
            }
        }
        $schema->table($model->getTable(), static fn (Blueprint $table) => $table->unique($columns, $name));
    }

    /** Backfill bounded actual records from explicit immutable per-record mappings. */
    public function backfill(OwnershipAdoptionPlan $plan, ?string $cursor, int $limit): OwnershipBackfillResult
    {
        return (new OwnershipResourceBackfill($this->resources, $this->mappings))->run($plan, $this->resources(), $cursor, $limit, 'Forms');
    }

    /** Verify persisted partitions and the complete canonical domain graph. */
    public function verify(OwnershipAdoptionPlan $plan): OwnershipVerification
    {
        foreach ($this->resources() as $key) {
            $this->schema->verify($key);
        }

        return new OwnershipVerification([]);
    }

    /** Constrain every native parent and scope edge before activation. */
    public function activate(OwnershipAdoptionPlan $plan): void
    {
        foreach ($this->resources() as $key) {
            $this->schema->constrain($key);
        }
    }
}
