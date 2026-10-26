<?php

declare(strict_types=1);
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormEntry;
use Nvl\Forms\Services\FormReader;
use Nvl\Forms\Tests\Stubs\TestFormsUser;

test('show form action records a view and loads related data', function (): void {
    $form = Form::factory()->create(['views_count' => 0]);
    FormEntry::factory()->count(3)->for($form)->create();
    $result = app(FormReader::class)->details($form, true, 'https://landing.example.com', '127.0.0.1', 'Mozilla/5.0', 'session-123', TestFormsUser::factory()->create());
    expect($result->id)->toBe($form->id)->and($result->views_count)->toBe(1)->and($result->relationLoaded('entries'))->toBeTrue()->and($result->entries)->toHaveCount(3)->and($result->relationLoaded('analytics'))->toBeTrue();
});
test('show form action skips recording when disabled', function (): void {
    $form = Form::factory()->create(['views_count' => 2]);
    $result = app(FormReader::class)->details($form->id, false, actor: formActor());
    expect($result->views_count)->toBe(2);
});
