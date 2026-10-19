<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures;

use Nvl\Forms\Contracts\OwnershipEntrySubmissionCallback;
use Nvl\Forms\Data\OwnershipFormSubmissionCallbackContext;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormEntry;

/** Records actual post-commit callback delivery for public ownership tests. */
final class OwnedEntryCallback implements OwnershipEntrySubmissionCallback
{
    public static array $calls = [];

    public function after(Form $form, FormEntry $entry, OwnershipFormSubmissionCallbackContext $context): void
    {
        self::$calls[] = [$context->owner->key, $context->site, $entry->id];
    }
}
