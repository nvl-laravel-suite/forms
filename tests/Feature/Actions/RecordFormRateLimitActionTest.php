<?php

declare(strict_types=1);
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormRateLimit;
use Nvl\Forms\Services\FormAnalytics;

test('record form rate limit action stores submission attempts', function (): void {
    $form = Form::factory()->create(['enable_rate_limiting' => true, 'rate_limit_per_hour' => 5]);
    app(FormAnalytics::class)->rateLimit($form, '127.0.0.1');
    $this->assertDatabaseHas(FormRateLimit::query()->getModel()->getTable(), ['form_id' => $form->id, 'ip_address' => '127.0.0.1']);
});
