<?php

declare(strict_types=1);

namespace Nvl\Forms\Contracts;

use Nvl\Forms\Models\Form;

/**
 * Defines the supported get form analytics summary workflow.
 *
 * @api
 */
interface GetFormAnalyticsSummaryContract
{
    /**
     * Build an analytics summary for the provided form.
     *
     * @param  Form|string  $form  Form instance or identifier
     * @param  int  $days  Rolling window in days to include in counts
     * @return array{total_views:int,total_submissions:int,spam_blocked:int,conversion_rate:float,top_origins:array<string,int>}
     */
    public function execute(Form|string $form, int $days = 30): array;
}
