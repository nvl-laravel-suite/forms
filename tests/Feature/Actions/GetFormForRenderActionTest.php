<?php

declare(strict_types=1);
use Illuminate\Support\Str;
use Nvl\Forms\Models\AllowedOrigin;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Services\FormReader;

test('get form for render action loads form by identifier with relations', function (): void {
    $form = Form::factory()->create(['handle' => 'shared-form']);
    AllowedOrigin::factory()->for($form)->create();
    $resolved = app(FormReader::class)->get('shared-form');
    expect($resolved->id)->toBe($form->id)->and($resolved->relationLoaded('allowedOrigins'))->toBeTrue();
});
test('get form for render action throws when form missing', function (): void {
    $this->expectException(Exception::class);
    app(FormReader::class)->get((string) Str::uuid());
});
