<?php

test('forms doctor rejects enabled public routes without throttling', function (): void {
    config(['nvl-forms.routes.public.middleware' => ['api']]);
    $check = collect(app(FormsDoctor::class)->inspect())->firstWhere('key', 'routes.public.throttle');
    expect($check)->not->toBeNull()->and($check['passed'])->toBeFalse();
});
