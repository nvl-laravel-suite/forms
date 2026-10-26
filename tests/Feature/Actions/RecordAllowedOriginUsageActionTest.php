<?php

declare(strict_types=1);
use Nvl\Forms\Models\AllowedOrigin;
use Nvl\Forms\Services\FormAnalytics;

test('record allowed origin usage action increments counter and timestamps', function (): void {
    $origin = AllowedOrigin::factory()->create(['usage_count' => 0, 'last_used_at' => null]);
    app(FormAnalytics::class)->originUsage($origin);
    $origin->refresh();
    expect($origin->usage_count)->toBe(1)->and($origin->last_used_at)->not->toBeNull();
});
test('record allowed origin usage action increments from existing count', function (): void {
    $origin = AllowedOrigin::factory()->create(['usage_count' => 5]);
    app(FormAnalytics::class)->originUsage($origin);
    expect($origin->fresh()->usage_count)->toBe(6);
});
