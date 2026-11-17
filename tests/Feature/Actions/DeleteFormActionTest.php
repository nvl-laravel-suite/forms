<?php

declare(strict_types=1);
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
test('delete form action refuses a form whose identity was modified in memory', function (): void {
    $withEntries = Form::factory()->create();
    FormEntry::factory()->for($withEntries)->create();
    $empty = Form::factory()->create();
    $withEntries->id = $empty->id;

    expect(fn () => app(FormLifecycle::class)->delete($withEntries, actor: formActor()))
        ->toThrow(ModelNotFoundException::class);
    $this->assertNotSoftDeleted(FormsTables::Forms, ['id' => $withEntries->getOriginal('id')]);
    $this->assertNotSoftDeleted(FormsTables::Forms, ['id' => $empty->id]);
});
test('delete form action checks dependencies on the stored form', function (): void {
    $form = Form::factory()->create();
    FormEntry::factory()->for($form)->create();
    $form->setRelation('entries', collect());

    expect(fn () => app(FormLifecycle::class)->delete($form, actor: formActor()))
        ->toThrow(Exception::class, trans('nvl-forms::forms/messages.error.cannot_delete_with_entries'));
    $this->assertNotSoftDeleted(FormsTables::Forms, ['id' => $form->id]);
});
