<?php

declare(strict_types=1);

namespace Nvl\Forms\Services;

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Builder;
use Nvl\Forms\Models\Form;
use Nvl\Support\Models\ConfiguredModelResolver;
use Nvl\Support\OwnerRegistry;
use Nvl\Support\Ownership\Contracts\OwnershipWriter;
use Nvl\Support\Ownership\Exceptions\OwnershipConfigurationInvalid;

/**
 * Resolves the one configured Form extension through the shared native model identity.
 *
 * @api
 */
final class FormModelRegistry
{
    /** @var class-string<Form>|null */
    private ?string $definition = null;

    private bool $validated = false;

    /** Retain deployment configuration and the shared identity registry. */
    public function __construct(
        private readonly Repository $configuration,
        private readonly OwnerRegistry $identities,
    ) {}

    /** Resolve and freeze the concrete Form class before resource compilation.
     * @internal
     *
     * @return class-string<Form>
     */
    public function prepare(): string
    {
        if ($this->definition !== null) {
            return $this->definition;
        }
        $configured = $this->configuration->get('nvl-forms.models.form', Form::class);
        if (! is_string($configured)) {
            throw new OwnershipConfigurationInvalid('Form model configuration must name a concrete Form subclass.');
        }
        $class = (new ConfiguredModelResolver)->resolve($configured, Form::class);
        if (! in_array($class::ownershipDefinition()->strategy, ['unscoped', 'column', 'pivot'], true)) {
            throw new OwnershipConfigurationInvalid('Form roots require a native column or concrete primary pivot.');
        }
        $this->identities->registerReplacement('forms.forms', Form::class, $class);
        $this->definition = $class;

        return $class;
    }

    /** Validate native capabilities after Laravel installs Eloquent's dispatcher.
     * @return class-string<Form>
     */
    public function formClass(): string
    {
        $class = $this->prepare();
        if ($this->validated) {
            return $class;
        }
        $model = new $class;
        $base = new Form;
        (new ConfiguredModelResolver)->assertOwnershipCapabilities($model, $base);
        $this->validated = true;

        return $class;
    }

    /** Resolve the immutable model from native framework entry points.
     * @internal
     *
     * @return class-string<Form>
     */
    public static function modelClass(): string
    {
        return Container::getInstance()->make(self::class)->formClass();
    }

    /** Construct the configured root.
     * @internal
     *
     * @param  array<string,mixed>  $attributes
     */
    public static function newInstance(array $attributes = []): Form
    {
        $class = Container::getInstance()->make(self::class)->formClass();

        return new $class($attributes);
    }

    /** Persist a package-authored attribute map under the exact source assignment.
     * @internal
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function create(array $attributes): Form
    {
        return app(OwnershipWriter::class)->create(self::modelClass(), static function (array $ownership) use ($attributes): Form {
            $model = self::newInstance();
            $model->forceFill([...$attributes, ...$ownership])->save();

            return $model;
        });
    }

    /** Persist a package-authored replica under a new exact primary assignment. @internal */
    public static function persist(Form $model): Form
    {
        return app(OwnershipWriter::class)->create(self::modelClass(), static function (array $attributes) use ($model): Form {
            $model->forceFill($attributes)->saveQuietly();

            return $model;
        });
    }

    /** Keep primary ownership facts in bounded native projections.
     * @param  list<string>  $columns
     * @return list<string>
     */
    public static function projectionColumns(array $columns): array
    {
        $definition = self::modelClass()::ownershipDefinition();
        if ($definition->strategy === 'unscoped') {
            return $columns;
        }

        return array_values(array_unique([...$columns, 'owner_scope_key', ...($definition->foreignKey === null ? [] : [$definition->foreignKey])]));
    }

    /** Create the exact configured query.
     * @internal
     *
     * @return Builder<Form>
     */
    public static function query(): Builder
    {
        return self::newInstance()->newQuery();
    }
}
