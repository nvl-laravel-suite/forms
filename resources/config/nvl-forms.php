<?php

declare(strict_types=1);

use Nvl\Forms\Models\Form;

return [
    'models' => ['form' => Form::class],
    'connection' => null,
    'migrations' => ['enabled' => true],
    'authorization' => ['gate' => null],
];
