<?php

declare(strict_types=1);

namespace Nvl\Forms\Contracts;

use Nvl\Forms\Results\FormSearchResult;

/**
 * Defines the supported search forms workflow.
 *
 * @api
 */
interface SearchFormsContract
{
    /**
     * Execute the form search.
     *
     * @param  array<string, mixed>  $filters  Search filters and parameters
     */
    public function execute(array $filters): FormSearchResult;
}
