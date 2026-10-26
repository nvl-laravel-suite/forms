<?php

declare(strict_types=1);
use Nvl\Forms\Models\Form;
use Nvl\Forms\Services\FormReader;

test('list forms action returns a paginator with the requested size', function (): void {
    Form::factory()->count(5)->create();
    $paginator = app(FormReader::class)->paginate(true, 2, actor: formActor());
    expect($paginator->perPage())->toBe(2)->and($paginator->total())->toBe(5);
});
test('list forms action can return a collection without pagination', function (): void {
    Form::factory()->count(3)->create();
    $collection = app(FormReader::class)->paginate(false, actor: formActor());
    expect($collection)->toHaveCount(3);
});
