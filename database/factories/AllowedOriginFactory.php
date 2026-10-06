<?php

declare(strict_types=1);

namespace Nvl\Forms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nvl\Forms\Models\AllowedOrigin;
use Nvl\Forms\Models\Form;

/**
 * Builds native package fixture rows and declared parents.
 *
 * @api
 *
 * @extends Factory<AllowedOrigin>
 */
final class AllowedOriginFactory extends Factory
{
    protected $model = AllowedOrigin::class;

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
        })->afterMaking(function (AllowedOrigin $model) use (&$expandRelationships): void {
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
     * Define the model's default state.
     *
     * @return array<model-property<AllowedOrigin>, mixed>
     */
    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'origin' => $this->faker->unique()->domainName(),
            'is_active' => true,
            'description' => $this->faker->optional()->sentence(),
            'cors_settings' => null,
            'usage_count' => 0,
            'last_used_at' => null,
        ];
    }

    /** Associate a persisted form parent.
     *
     * @api
     */
    public function forForm(Form $form): static
    {
        FactoryGuard::parent($form, new AllowedOrigin);

        return $this->state(['form_id' => $form->getKey()]);
    }
}
