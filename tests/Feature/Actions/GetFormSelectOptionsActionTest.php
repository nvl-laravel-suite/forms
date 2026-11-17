<?php

declare(strict_types=1);
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Services\FormReader;

test('get form select options action filters by public visibility and submissions', function (): void {
    $public = Form::factory()->create(['name' => 'Public Form', 'restrict_public_access' => false, 'submissions_count' => 3]);
    Form::factory()->create(['name' => 'Private Form', 'restrict_public_access' => true, 'submissions_count' => 10]);
    $options = app(FormReader::class)->options(['publicOnly' => true, 'withSubmissions' => true], formActor());
    expect($options)->toHaveCount(1)->and($options->first()['id'])->toBe($public->id)->and($options->first()['sublabel'])->toBe($public->handle);
});
test('get form select options action ignores ambient request filters', function (): void {
    $public = Form::factory()->create(['name' => 'Public Form', 'restrict_public_access' => false, 'submissions_count' => 3]);
    Form::factory()->create(['name' => 'Private Form', 'restrict_public_access' => true, 'submissions_count' => 10]);
    app()->instance('request', Request::create('/nvl/api/v1/forms/select', 'GET', ['q' => 'Private', 'publicOnly' => false]));
    $options = app(FormReader::class)->options(['publicOnly' => true, 'withSubmissions' => true], formActor());
    expect($options)->toHaveCount(1)->and($options->first()['id'])->toBe($public->id);
});
test('form select options require an authorized management actor', function (bool $denied): void {
    Form::factory()->create(['name' => 'Draft demo', 'handle' => 'draft-demo', 'status' => 'draft', 'restrict_public_access' => true]);
    if ($denied) {
        Gate::define('manage-forms', static fn (): bool => false);
    }
    $actor = $denied ? formActor() : null;

    expect(fn () => app(FormReader::class)->options([], $actor))->toThrow(AuthorizationException::class);
})->with(['missing actor' => [false], 'denied actor' => [true]]);
