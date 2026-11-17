<?php

declare(strict_types=1);
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Services\FormReader;

test('get form suggestions action ranks and limits results', function (): void {
    $first = Form::factory()->create(['name' => 'Demo Signup', 'handle' => 'demo-signup', 'submissions_count' => 5]);
    $second = Form::factory()->create(['name' => 'Product Demo', 'handle' => 'product-demo', 'submissions_count' => 10]);
    Form::factory()->create(['name' => 'Support Request', 'handle' => 'support-request']);
    $results = app(FormReader::class)->suggestions('demo', 2, formActor());
    expect($results)->toHaveCount(2)->and($results->first())->toBeInstanceOf(Form::class)->and($results->first()->id)->toBe($second->id)->and($results->first()->displayName())->toBe('Product Demo');
});
test('form suggestions require an authorized management actor', function (bool $denied): void {
    Form::factory()->create(['name' => 'Draft demo', 'handle' => 'draft-demo', 'status' => 'draft', 'restrict_public_access' => true]);
    if ($denied) {
        Gate::define('manage-forms', static fn (): bool => false);
    }
    $actor = $denied ? formActor() : null;

    expect(fn () => app(FormReader::class)->suggestions('demo', actor: $actor))->toThrow(AuthorizationException::class);
})->with(['missing actor' => [false], 'denied actor' => [true]]);
