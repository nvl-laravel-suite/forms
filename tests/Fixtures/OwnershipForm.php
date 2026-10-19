<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures;

use Nvl\Forms\Models\Form;
use Nvl\Support\Ownership\ValueObjects\OwnershipDefinition;

/** Consumer Form extension with a native primary owner. */
class OwnershipForm extends Form
{
    public static function ownershipDefinition(): OwnershipDefinition
    {
        return OwnershipDefinition::column(Company::class, 'company_id');
    }
}
