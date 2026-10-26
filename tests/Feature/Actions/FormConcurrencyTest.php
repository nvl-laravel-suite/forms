<?php

declare(strict_types=1);
use Nvl\Forms\Exceptions\FormException;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Services\FormLifecycle;
use Nvl\Forms\Tests\Fixtures\Consumer\Data\Mutations\MutateFormPayload;

test('form updates reject stale revisions', function (): void {
    $form = Form::factory()->create();
    app(FormLifecycle::class)->update($form, formNativeInput(MutateFormPayload::from(['handle' => 'first-writer', 'expectedRevision' => 1])), actor: formActor());
    expect(fn () => app(FormLifecycle::class)->update($form, formNativeInput(MutateFormPayload::from(['handle' => 'stale-writer', 'expectedRevision' => 1])), actor: formActor()))->toThrow(FormException::class, 'changed by another writer');
});
