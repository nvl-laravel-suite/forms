<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures;

use Nvl\Support\Ownership\ValueObjects\OwnershipDefinition;

/** A consumer's exclusive native primary-pivot declaration. */
final class PivotForm extends OwnershipForm
{
    public static function ownershipDefinition(): OwnershipDefinition
    {
        return OwnershipDefinition::pivot(Company::class, 'forms_owned_primary', 'form_id', 'company_id');
    }
}
