<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures;

use Nvl\Forms\Contracts\OwnershipEntrySubmissionCallback;
use Nvl\Forms\Data\OwnershipFormSubmissionCallbackContext;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormEntry;
use RuntimeException;

/** Reproduces a failing integration after durable submission commit. */
final class ThrowingOwnedEntryCallback implements OwnershipEntrySubmissionCallback
{
    public function after(Form $form, FormEntry $entry, OwnershipFormSubmissionCallbackContext $context): void
    {
        throw new RuntimeException('The downstream integration is unavailable.');
    }
}
