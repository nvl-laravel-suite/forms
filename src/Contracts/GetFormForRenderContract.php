<?php

declare(strict_types=1);

namespace Nvl\Forms\Contracts;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Nvl\Forms\Models\Form;

/**
 * Defines the supported get form for render workflow.
 *
 * @api
 */
interface GetFormForRenderContract
{
    /**
     * Execute the form retrieval for rendering.
     *
     * Accepts a pre-loaded Form instance to avoid redundant queries
     * when the model was already resolved by middleware.
     *
     * @param  Form|string  $formIdentifier  Form model, ID, or handle
     * @return Form Form model with loaded relationships
     *
     * @throws ModelNotFoundException If the form cannot be resolved
     */
    public function execute(Form|string $formIdentifier): Form;
}
