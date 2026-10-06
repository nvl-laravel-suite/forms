<?php

declare(strict_types=1);

namespace Nvl\Forms\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Nvl\Filterable\Data\FilterSet;
use Nvl\Forms\Exceptions\FormException;
use Nvl\Forms\Models\Form;

/**
 * Defines the supported list forms workflow.
 *
 * @api
 */
interface ListFormsContract
{
    /**
     * Execute the form listing.
     *
     * @param  bool  $paginate  Whether to paginate results
     * @param  int|null  $perPage  Items per page when paginating
     * @return LengthAwarePaginator<int, Form>|Collection<int, Form>
     *
     * @throws FormException When per_page parameter is invalid
     */
    public function execute(
        bool $paginate = true,
        ?int $perPage = null,
        ?FilterSet $filters = null,
    ): LengthAwarePaginator|Collection;
}
