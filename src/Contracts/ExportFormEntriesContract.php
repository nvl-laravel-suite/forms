<?php

declare(strict_types=1);

namespace Nvl\Forms\Contracts;

use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Nvl\Forms\Models\Form;

/**
 * Defines the supported export form entries workflow.
 *
 * @api
 */
interface ExportFormEntriesContract
{
    /**
     * Execute the form entries export with permission checking and progress tracking.
     *
     * Validates permissions, cleans up old exports, fetches matching entries,
     * delegates CSV generation to the export service, saves the file, and logs activity.
     *
     * @param  Form|string  $form  Form instance or identifier
     * @param  array<string, mixed>  $options  Export options (date_from, date_to, limit, has_contact_info, include_submission_data, include_sensitive_data)
     * @param  Authenticatable|null  $actor  Authenticated actor requesting the export
     * @return string Absolute path to the generated CSV file
     *
     * @throws Exception When permission denied, no data to export, or export fails
     */
    public function execute(Form|string $form, array $options = [], ?Authenticatable $actor = null): string;
}
