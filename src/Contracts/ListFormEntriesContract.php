<?php

declare(strict_types=1);

namespace Nvl\Forms\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Nvl\Filterable\Data\FilterSet;
use Nvl\Forms\Exceptions\FormException;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormEntry;

/**
 * Defines the supported list form entries workflow.
 *
 * @api
 */
interface ListFormEntriesContract
{
    /**
     * Execute the form entries listing.
     *
     * @param  Form|string|null  $form  Form instance, identifier, or null for all entries
     * @param  bool  $paginate  Whether to paginate results
     * @param  int|null  $perPage  Items per page when paginating
     * @return LengthAwarePaginator<int, FormEntry>|Collection<int, FormEntry>
     *
     * @throws FormException When per_page parameter is invalid
     */
    public function execute(
        Form|string|null $form = null,
        bool $paginate = true,
        ?int $perPage = null,
        ?FilterSet $filters = null,
    ): LengthAwarePaginator|Collection;
}
