<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures;

use Nvl\Forms\Contracts\CustomFormHandler;
use Nvl\Forms\Models\Form;

/** A named handler instance registered directly, without an invokable method. */
final class RecordingFormHandler implements CustomFormHandler
{
    public function handle(Form $form, array $data, array $context): array
    {
        return ['entry_id' => 'recorded'];
    }
}
