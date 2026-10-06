<?php

declare(strict_types=1);

namespace Nvl\Forms\Contracts;

use Nvl\Forms\Models\Form;

/**
 * Defines the supported get form analytics bundle workflow.
 *
 * @api
 */
interface GetFormAnalyticsBundleContract
{
    /**
     * Fetch a form alongside analytics data and a small sample of recent entries.
     *
     * @param  Form|string  $form  Form instance or identifier
     * @param  int  $analyticsDays  Rolling window for analytics aggregation
     * @return array{form: Form, analytics: array<string, mixed>, recent_entries: array<int, array<string, mixed>>}
     */
    public function execute(Form|string $form, int $analyticsDays = 30): array;
}
