<?php

declare(strict_types=1);

namespace Nvl\Forms\Contracts;

use Illuminate\Support\Collection;
use Nvl\Forms\Models\Form;

/**
 * Defines the supported get form suggestions workflow.
 *
 * @api
 */
interface GetFormSuggestionsContract
{
    /**
     * Execute the form suggestions retrieval.
     *
     * @param  string  $query  Search query term
     * @param  int  $limit  Maximum number of suggestions to return
     * @return Collection<int, Form>
     */
    public function execute(string $query, int $limit = 10): Collection;
}
