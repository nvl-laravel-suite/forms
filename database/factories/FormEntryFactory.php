<?php

declare(strict_types=1);

namespace Nvl\Forms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormEntry;

/**
 * Builds native package fixture rows and declared parents.
 *
 * @api
 *
 * @extends Factory<FormEntry>
 */
final class FormEntryFactory extends Factory
{
    protected $model = FormEntry::class;

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
        })->afterMaking(function (FormEntry $model) use (&$expandRelationships): void {
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
     * @return array<model-property<FormEntry>, mixed>
     */
    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'subject' => $this->faker->optional()->sentence(),
            'email' => $this->faker->optional()->safeEmail(),
            'first_name' => $this->faker->optional()->firstName(),
            'last_name' => $this->faker->optional()->lastName(),
            'phone' => $this->faker->optional()->phoneNumber(),
            'address' => $this->faker->optional()->address(),
            'body' => $this->faker->optional()->paragraph(),
            'submission_data' => ['message' => $this->faker->sentence()],
            'submitted_from' => $this->faker->domainName(),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'session_id' => $this->faker->uuid(),
            'is_spam' => false,
            'spam_score' => null,
            'security_flags' => null,
        ];
    }

    /** Associate a persisted form parent.
     *
     * @api
     */
    public function forForm(Form $form): static
    {
        FactoryGuard::parent($form, new FormEntry);

        return $this->state(['form_id' => $form->getKey()]);
    }
}
