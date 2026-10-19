<?php

declare(strict_types=1);

namespace Nvl\Forms\Contracts;

use Nvl\Forms\Data\OwnershipFormSubmissionCallbackContext;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormEntry;

/**
 * A freshly constructed committed callback without retained Request or owner-scoped objects.
 *
 * @api
 */
interface OwnershipEntrySubmissionCallback
{
    /** Handle the canonical owner-local form and entry under freshly verified public admission. */
    public function after(Form $form, FormEntry $entry, OwnershipFormSubmissionCallbackContext $context): void;
}
