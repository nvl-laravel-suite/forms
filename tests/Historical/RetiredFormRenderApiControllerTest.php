<?php

test('public render tokens retain the selected canonical or legacy site attribute', function (string $attributeKey): void {
    $form = Form::factory()->create(['restrict_public_access' => false, 'status' => FormStatus::ACTIVE]);
    $request = Request::create('https://forms.test/render');
    $request->attributes->set($attributeKey, new TenantSiteContext(new TenantId('10000000-0000-4000-8000-000000000001'), 'verified', 'https://forms.test'));
    $response = app()->call([app(FormRenderApiController::class), 'show'], ['request' => $request, 'formIdentifier' => $form->id]);
    $token = $response->getData(true)['public_token'] ?? null;
    expect($token)->toBeString();
    $payload = json_decode(base64_decode(strtr(explode('.', $token, 2)[0], '-_', '+/')), true, flags: JSON_THROW_ON_ERROR);
    expect($response->getStatusCode())->toBe(200)->and(app(PublicFormTokenService::class)->validate($token, $form, 'verified'))->toBeTrue()->and($payload['site'] ?? null)->toBe('verified');
})->with([TenantSiteContext::class, TenantSiteAttributes::LegacyKey]);
