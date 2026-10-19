<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Nvl\Forms\Services\RequestOriginResolver;
use Nvl\Forms\Support\FormSubmissionContext;
use Nvl\Forms\Tests\FormsTestCase;
use Nvl\Forms\Tests\LegacyOwnershipTestCase;
use Nvl\Forms\Tests\OwnershipTestCase;
use Nvl\Forms\Tests\PivotOwnershipTestCase;
use Nvl\Forms\Tests\TenancyTestCase;

uses(FormsTestCase::class)->in('Feature');
uses(TenancyTestCase::class)->in('Tenancy');

function formSubmissionContext(Request $request): FormSubmissionContext
{
    return FormSubmissionContext::fromRequest(
        $request,
        app(RequestOriginResolver::class),
    );
}

uses(OwnershipTestCase::class)->in('Ownership');

uses(LegacyOwnershipTestCase::class)->in('OwnershipAdoption');

pest()->extend(PivotOwnershipTestCase::class)->in(__DIR__.'/OwnershipPivot');
