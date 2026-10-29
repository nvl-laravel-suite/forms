<?php

declare(strict_types=1);
use Illuminate\Support\Facades\DB;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormEntry;
use Nvl\Forms\Models\FormTranslation;
use Nvl\Forms\Services\FormReader;

test('search forms action filters by submissions and limit', function (): void {
    Form::factory()->create(['name' => 'Active Form', 'submissions_count' => 5]);
    Form::factory()->create(['name' => 'Inactive Form', 'submissions_count' => 0]);
    $result = app(FormReader::class)->search(['has_submissions' => true, 'limit' => 1], actor: formActor());
    expect($result['forms'])->toHaveCount(1)->and($result['forms']->first()->displayName())->toBe('Active Form')->and($result['total'])->toBe(1);
});
test('search forms action can eager load relations safely', function (): void {
    $form = Form::factory()->create();
    $form->entries()->create(['subject' => 'Hello', 'submitted_from' => 'example.com']);
    $result = app(FormReader::class)->search(['with' => ['entries'], 'limit' => 10], actor: formActor());
    expect($result['forms']->first()->relationLoaded('entries'))->toBeTrue()->and($result['total'])->toBe(1);
});
test('search projections keep a constant query count as forms grow', function (): void {
    $create = static function (int $index): void {
        $form = Form::factory()->create(['name' => "Query Form {$index}"]);
        $form->entries()->create(['subject' => "Entry {$index}", 'submitted_from' => 'example.com']);
    };
    $actor = formActor();
    $measure = static function () use ($actor): array {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $result = app(FormReader::class)->search(['with' => ['entries'], 'limit' => 30], actor: $actor);
        $queryCount = count(DB::getQueryLog());
        foreach ($result['forms'] as $form) {
            $form->entries->count();
        }
        expect(DB::getQueryLog())->toHaveCount($queryCount);
        DB::disableQueryLog();

        $tables = [new Form, new FormEntry, new FormTranslation];
        $wrapped = array_map(static fn ($model): string => $model->getConnection()->getQueryGrammar()->wrapTable($model->getTable()), $tables);
        $dataQueries = array_filter(DB::getQueryLog(), static fn (array $query): bool => collect($wrapped)->contains(static fn (string $table): bool => str_contains($query['query'], 'from '.$table)));

        return ['total' => $queryCount, 'data' => count($dataQueries), 'metadata' => $queryCount - count($dataQueries)];
    };
    $create(1);
    $singleQueryCount = $measure();
    foreach (range(2, 25) as $index) {
        $create($index);
    }
    $populatedQueryCount = $measure();
    expect($singleQueryCount['data'])->toBeGreaterThan(0)->toBeLessThanOrEqual(4)->and($populatedQueryCount)->toBe($singleQueryCount);
});
