<?php

declare(strict_types=1);

namespace Nvl\Forms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormTranslation;

/**
 * Builds FormTranslation fixture rows and their declared package parents.
 *
 * @extends Factory<FormTranslation>
 *
 * @api
 */
final class FormTranslationFactory extends Factory
{
    protected $model = FormTranslation::class;

    /**
     * Prepare native parent and owner facts after Laravel expands relationships.
     *
     * @internal
     */
    public function configure(): static
    {
        $expandRelationships = true;

        return $this->state(function () use (&$expandRelationships): array {
            $expandRelationships = $this->expandRelationships;

            return [];
        })->afterMaking(function (FormTranslation $model) use (&$expandRelationships): void {
            if (! $expandRelationships) {
                return;
            }

            if ($model->getAttribute('form_id') !== null) {
                $parent = Form::query()->findOrFail(FactoryGuard::identifier($model->getAttribute('form_id')));
                FactoryGuard::parent($parent, $model);
                FactoryGuard::inherit($model, $parent);
            }
        });
    }

    /**
     * Define the fixture's persisted attributes.
     *
     * @return array<model-property<FormTranslation>, mixed>
     */
    public function definition(): array
    {
        return [
            'form_id' => Form::factory()->withoutTranslations(),
            'locale' => 'en',
            'name' => $this->faker->sentence(3),
        ];
    }

    /**
     * Associate an admitted persisted Form parent.
     *
     * @api
     */
    public function forForm(Form $parent): static
    {
        FactoryGuard::parent($parent, new FormTranslation);

        return $this->state([
            'form_id' => $parent->getKey(),
        ]);
    }
}
