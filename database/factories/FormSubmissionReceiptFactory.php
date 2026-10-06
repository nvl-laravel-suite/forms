<?php

declare(strict_types=1);

namespace Nvl\Forms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nvl\Forms\Enums\FormSubmissionReceiptState;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormSubmissionReceipt;

/**
 * Builds native package fixture rows and declared parents.
 *
 * @api
 *
 * @extends Factory<FormSubmissionReceipt>
 */
final class FormSubmissionReceiptFactory extends Factory
{
    protected $model = FormSubmissionReceipt::class;

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
        })->afterMaking(function (FormSubmissionReceipt $model) use (&$expandRelationships): void {
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
     * @return array<model-property<FormSubmissionReceipt>, mixed>
     */
    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'idempotency_key' => null,
            'payload_digest' => hash('sha256', $this->faker->uuid()),
            'registration_fingerprint' => null,
            'state' => FormSubmissionReceiptState::Completed,
            'result_id' => $this->faker->uuid(),
        ];
    }

    /** Associate a persisted form parent.
     *
     * @api
     */
    public function forForm(Form $form): static
    {
        FactoryGuard::parent($form, new FormSubmissionReceipt);

        return $this->state(['form_id' => $form->getKey()]);
    }
}
