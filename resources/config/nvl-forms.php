<?php

declare(strict_types=1);

return [
    'migrations' => ['enabled' => true],
    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | Both surfaces are opt-in. Management middleware must authenticate callers,
    | while package policies additionally require an explicit gate.
    |
    */
    'routes' => ['prefix' => 'nvl/api/v1', 'middleware' => ['api'], 'management' => ['enabled' => false, 'middleware' => ['auth']], 'public' => ['enabled' => false, 'middleware' => ['throttle:nvl.forms.public']]],
    'authorization' => ['gate' => null],
];
