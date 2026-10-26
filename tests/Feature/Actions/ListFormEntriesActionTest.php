<?php

declare(strict_types=1);
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormEntry;
use Nvl\Forms\Services\FormEntries;

test('list form entries action filters by form and paginates', function (): void {
    $form = Form::factory()->create();
    FormEntry::factory()->count(3)->for($form)->create();
    FormEntry::factory()->create();
    // Different form
    $paginator = app(FormEntries::class)->paginate($form, true, 2, actor: formActor());
    expect($paginator->total())->toBe(3)->and($paginator->perPage())->toBe(2);
});
