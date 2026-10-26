<?php

declare(strict_types=1);
use Nvl\Forms\Definitions\Tables\FormsTables;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormEntry;
use Nvl\Forms\Services\FormLifecycle;

test('delete form action removes a form without dependencies', function (): void {
    $form = Form::factory()->create();
    $result = app(FormLifecycle::class)->delete($form, actor: formActor());
    expect($result)->toBeTrue();
    $this->assertSoftDeleted(FormsTables::Forms, ['id' => $form->id]);
});
test('delete form action throws when form has entries', function (): void {
    $form = Form::factory()->create();
    FormEntry::factory()->for($form)->create();
    $this->expectException(Exception::class);
    $this->expectExceptionMessage(trans('nvl-forms::forms/messages.error.cannot_delete_with_entries'));
    app(FormLifecycle::class)->delete($form, actor: formActor());
});
