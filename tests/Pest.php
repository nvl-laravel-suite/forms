<?php

declare(strict_types=1);
use Illuminate\Http\Request;
use Nvl\Forms\Models\FormEntry;
use Nvl\Forms\Models\FormSubmissionReceipt;
use Nvl\Forms\Services\FormSubmissions;
use Nvl\Forms\Tests\Fixtures\Consumer\FormSubmissionContext;
use Nvl\Forms\Tests\Fixtures\Consumer\Services\RequestOriginResolver;
use Nvl\Forms\Tests\FormsTestCase;
use Nvl\Forms\Tests\OwnershipTestCase;
use Nvl\Forms\Tests\PivotOwnershipTestCase;
use Nvl\Forms\Tests\Stubs\TestFormsUser;

uses(FormsTestCase::class)->in('Feature');
function formSubmissionContext(Request $request): FormSubmissionContext
{
    return FormSubmissionContext::fromRequest($request, app(RequestOriginResolver::class));
}
uses(OwnershipTestCase::class)->in('Ownership');
pest()->extend(PivotOwnershipTestCase::class)->in(__DIR__.'/OwnershipPivot');

/** The consumer fixture converts its own DTOs before calling a native contract. */
function formNativeInput(mixed $input): array
{
    return is_array($input) ? $input : $input->toArray();
}

function formActor(): TestFormsUser
{
    return (new TestFormsUser)->forceFill(['id' => 1, 'name' => 'Forms manager']);
}

/** Retained unit scenarios for private security stages; complete submission is covered independently. */
function formSubmissionStage(string $method, mixed ...$arguments): mixed
{
    $service = app(FormSubmissions::class);
    $contextIndex = match ($method) {
        'prepare', 'guardCustom' => 2,
        'validateProtection' => 1,
        default => null,
    };
    if ($contextIndex !== null) {
        $arguments[$contextIndex] = (new ReflectionMethod($service, 'protection'))->invoke($service, $arguments[$contextIndex]);
    }

    return (new ReflectionMethod($service, $method))->invokeArgs($service, $arguments);
}

function formSubmissionId(FormEntry|FormSubmissionReceipt $result): string
{
    return $result instanceof FormEntry ? $result->id : ($result->result_id ?? '');
}
