<?php

declare(strict_types=1);

namespace Nvl\Forms\Services;

use Closure;
use Nvl\Forms\Contracts\CustomFormHandler;
use Nvl\Forms\Models\Form;

final class CallbackFormHandler implements CustomFormHandler
{
    /** @var Closure(Form,array<string,mixed>,array<string,mixed>):array<string,mixed> */
    private Closure $callback;

    /**
     * @param  Closure(Form,array<string,mixed>,array<string,mixed>):array<string,mixed>  $callback
     */
    public function __construct(Closure $callback)
    {
        $this->callback = $callback;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function handle(Form $form, array $data, array $context): array
    {
        $cb = $this->callback;

        /** @var array<string,mixed> $resp */
        $resp = $cb($form, $data, $context);

        return $resp;
    }
}
