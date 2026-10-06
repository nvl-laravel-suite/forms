<?php

declare(strict_types=1);

namespace Nvl\Forms\Contracts;

use Illuminate\Support\Collection;
use Nvl\Forms\Data\FormSelectOptionItem;

/**
 * Defines the supported get form select options workflow.
 *
 * @api
 */
interface GetFormSelectOptionsContract
{
    /**
     * Execute the form select options retrieval.
     *
     * @param  array<string, mixed>  $filters  Filter parameters for form selection
     * @return Collection<int, FormSelectOptionItem>
     */
    public function execute(array $filters): Collection;
}
