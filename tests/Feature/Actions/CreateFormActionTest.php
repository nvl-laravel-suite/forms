<?php

declare(strict_types=1);
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Nvl\Forms\Enums\FormType;
use Nvl\Forms\Enums\Resolvement;
use Nvl\Forms\Events\FormChanged;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Services\FormLifecycle;
use Nvl\Forms\Tests\Stubs\TestFormsUser;

test('create form action persists a form with a generated handle', function (): void {
    Event::fake([FormChanged::class]);
    $data = ['translations' => ['en' => ['name' => 'Marketing Landing Page']], 'resolvement' => Resolvement::ENTRIES->value, 'type' => FormType::LANDING_PAGE->value];
    $actor = TestFormsUser::factory()->create();
    $form = app(FormLifecycle::class)->create(formNativeInput($data), $actor);
    expect($form->id)->not->toBeNull()->and($form->handle)->toBe('marketing-landing-page')->and($form->displayName())->toBe('Marketing Landing Page')->and($form->fresh())->not->toBeNull();
    Event::assertDispatched(FormChanged::class);
});
test('create form action honours a provided handle', function (): void {
    $data = ['translations' => ['en' => ['name' => 'Internal Feedback']], 'handle' => 'internal-feedback', 'resolvement' => Resolvement::ENTRIES->value, 'type' => FormType::IFRAME->value];
    $form = app(FormLifecycle::class)->create(formNativeInput($data), actor: formActor());
    expect($form->handle)->toBe('internal-feedback')->and($form->type->value)->toBe(FormType::IFRAME->value);
    $this->assertDatabaseHas(Form::query()->getModel()->getTable(), ['id' => $form->id, 'handle' => 'internal-feedback']);
});

test('native form creation rejects unknown and protected attributes', function (string $attribute): void {
    expect(fn () => app(FormLifecycle::class)->create([
        'translations' => ['en' => ['name' => 'Native form']],
        'resolvement' => Resolvement::ENTRIES->value,
        'type' => FormType::IFRAME->value,
        $attribute => 'untrusted',
    ], formActor()))->toThrow(ValidationException::class);

    expect(Form::query()->count())->toBe(0);
})->with(['unknown', 'owner_scope_key', 'revision', 'actor', 'options.theme']);
