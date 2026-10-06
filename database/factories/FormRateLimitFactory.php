<?php

declare(strict_types=1);

namespace Nvl\Forms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormRateLimit;

/**
 * Builds FormRateLimit fixture rows and their declared package parents.
 *
 * @extends Factory<FormRateLimit>
 *
 * @api
 */
final class FormRateLimitFactory extends Factory
{
    protected $model = FormRateLimit::class;

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
        })->afterMaking(function (FormRateLimit $model) use (&$expandRelationships): void {
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
     * @return array<model-property<FormRateLimit>, mixed>
     */
    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'ip_address' => $this->faker->ipv4(),
            'window_start' => now(),
            'last_submission_at' => now(),
            'submission_count' => 0,
            'violation_count' => 0,
            'is_blocked' => false,
        ];
    }

    /**
     * Associate an admitted persisted Form parent.
     *
     * @api
     */
    public function forForm(Form $parent): static
    {
        FactoryGuard::parent($parent, new FormRateLimit);

        return $this->state([
            'form_id' => $parent->getKey(),
        ]);
    }
}
