<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Validation\ValidationException;
use Nvl\Forms\Exceptions\FormSubmissionRejectionException;
use Nvl\Forms\Models\AllowedOrigin;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormEntry;
use Nvl\Forms\Models\FormRateLimit;
use Nvl\Forms\Services\CustomFormRegistry;
use Nvl\Forms\Services\FormAnalytics;
use Nvl\Forms\Services\FormEntries;
use Nvl\Forms\Services\FormEntryExportService;
use Nvl\Forms\Services\FormLifecycle;
use Nvl\Forms\Services\FormOriginAccessService;
use Nvl\Forms\Services\FormReader;
use Nvl\Forms\Services\FormsDoctor;
use Nvl\Forms\Services\FormSubmissions;
use Nvl\Forms\Support\FormHandlerRegistry;
use Nvl\Forms\Tests\Fixtures\RecordingFormHandler;

test('a restricted form accepts the exact port its allowed origin names', function (): void {
    $form = Form::factory()->create(['require_csrf' => false, 'enable_honeypot' => false, 'enable_rate_limiting' => false, 'restrict_public_access' => true]);
    AllowedOrigin::factory()->for($form)->create(['origin' => 'embed.example.test:8443']);
    $service = app(FormSubmissions::class);

    $entry = $service->submit($form, ['body' => 'A normal enquiry.'], ['origin' => 'https://embed.example.test:8443', 'ipAddress' => '192.0.2.10']);

    expect($entry)->toBeInstanceOf(FormEntry::class)
        ->and($entry->submitted_from)->toBe('embed.example.test:8443');
    expect(fn () => $service->submit($form, ['body' => 'Another enquiry.'], ['origin' => 'https://embed.example.test:9000', 'ipAddress' => '192.0.2.10']))
        ->toThrow(FormSubmissionRejectionException::class);
    expect(fn () => $service->submit($form, ['body' => 'Another enquiry.'], ['origin' => 'https://embed.example.test', 'ipAddress' => '192.0.2.10']))
        ->toThrow(FormSubmissionRejectionException::class);
});

test('the collection formatter keeps a backslash-quote value inside its own cell', function (): void {
    $form = Form::factory()->create();
    $body = 'safe\\",=1+1,\\"tail';
    FormEntry::factory()->for($form)->create(['body' => $body, 'submission_data' => null]);

    $csv = app(FormEntryExportService::class)->generateCsvContent($form, FormEntry::query()->where('form_id', $form->id)->get());
    $lines = explode("\n", trim($csv));
    $headers = str_getcsv($lines[0], ',', '"', '');
    $row = str_getcsv($lines[1], ',', '"', '');

    expect($lines)->toHaveCount(2)
        ->and($row)->toHaveCount(count($headers))
        ->and($row[array_search('Message', $headers, true)])->toBe($body);
});

test('a malformed identifier names no record on every driver', function (Closure $operation): void {
    Form::factory()->create();

    expect(fn () => $operation(formActor()))->toThrow(ModelNotFoundException::class);
})->with([
    'form details' => [fn ($actor) => app(FormReader::class)->details('not-a-uuid', false, actor: $actor)],
    'form summary' => [fn ($actor) => app(FormReader::class)->summary('not-a-uuid', actor: $actor)],
    'form update' => [fn ($actor) => app(FormLifecycle::class)->update('not-a-uuid', ['expected_revision' => 1], $actor)],
    'form duplicate' => [fn ($actor) => app(FormLifecycle::class)->duplicate('not-a-uuid', actor: $actor)],
    'form delete' => [fn ($actor) => app(FormLifecycle::class)->delete('not-a-uuid', $actor)],
    'form view' => [fn () => app(FormAnalytics::class)->view('not-a-uuid', null, null, null, null)],
    'form analytic' => [fn () => app(FormAnalytics::class)->record('not-a-uuid', 'view')],
    'form origin' => [fn () => app(FormOriginAccessService::class)->isOriginAllowed('not-a-uuid', 'example.com')],
    'entry export' => [fn ($actor) => app(FormEntries::class)->export('not-a-uuid', [], $actor)],
    'entry get' => [fn ($actor) => app(FormEntries::class)->get('not-a-uuid', $actor)],
    'entry update' => [fn ($actor) => app(FormEntries::class)->update('not-a-uuid', ['subject' => 'Changed'], $actor)],
    'entry delete' => [fn ($actor) => app(FormEntries::class)->delete('not-a-uuid', $actor)],
    'entry redact' => [fn ($actor) => app(FormEntries::class)->redact('not-a-uuid', ['email'], $actor)],
    'entry anonymize' => [fn ($actor) => app(FormEntries::class)->anonymize('not-a-uuid', $actor)],
    'entry flag' => [fn ($actor) => app(FormEntries::class)->flag('not-a-uuid', 'reviewed', true, $actor)],
    'entry spam' => [fn ($actor) => app(FormEntries::class)->markSpam('not-a-uuid', actor: $actor)],
    'entry legitimate' => [fn ($actor) => app(FormEntries::class)->markLegitimate('not-a-uuid', $actor)],
]);

test('entry lists treat a malformed form or address filter as matching nothing', function (): void {
    $form = Form::factory()->create();
    FormEntry::factory()->for($form)->create(['ip_address' => '192.0.2.44']);

    expect(app(FormEntries::class)->paginate('not-a-uuid', actor: formActor())->total())->toBe(0)
        ->and(app(FormEntries::class)->paginate($form, filters: ['ip_address' => 'not-an-address'], actor: formActor())->total())->toBe(0)
        ->and(app(FormEntries::class)->paginate($form, filters: ['ip_address' => '192.0.2.44'], actor: formActor())->total())->toBe(1);
});

test('the handle of a deleted form stays reserved instead of failing in the database', function (): void {
    $lifecycle = app(FormLifecycle::class);
    $input = ['translations' => ['en' => ['name' => 'Contact']], 'resolvement' => 'entries', 'type' => 'iframe'];
    $deleted = $lifecycle->create([...$input, 'handle' => 'contact'], formActor());
    $lifecycle->delete($deleted, formActor());

    expect(fn () => $lifecycle->create([...$input, 'handle' => 'contact'], formActor()))
        ->toThrow(Exception::class, trans('nvl-forms::forms/messages.error.handle_exists', ['handle' => 'contact']));
    expect($lifecycle->create($input, formActor())->handle)->toBe('contact-1');
});

test('forms created without a handle receive a unique generated one', function (): void {
    $lifecycle = app(FormLifecycle::class);
    $input = ['resolvement' => 'entries', 'type' => 'iframe'];

    expect($lifecycle->create([...$input, 'translations' => ['en' => ['name' => 'Newsletter']]], formActor())->handle)->toBe('newsletter')
        ->and($lifecycle->create([...$input, 'translations' => ['en' => ['name' => 'Newsletter']]], formActor())->handle)->toBe('newsletter-1')
        ->and($lifecycle->create([...$input, 'translations' => ['en' => ['name' => '!!!']]], formActor())->handle)->toBe('form');
});

test('form updates accept a numeric expected revision and keep required columns on explicit null', function (): void {
    $form = Form::factory()->create(['handle' => 'kept-handle', 'revision' => 1, 'rate_limit_per_hour' => 25]);

    $updated = app(FormLifecycle::class)->update($form, ['expected_revision' => '1', 'handle' => null, 'rateLimitPerHour' => null], formActor());

    expect($updated->revision)->toBe(2)
        ->and($updated->handle)->toBe('kept-handle')
        ->and($updated->rate_limit_per_hour)->toBe(25);
});

test('form creation keeps the default rate limit on explicit null', function (): void {
    $form = app(FormLifecycle::class)->create([
        'translations' => ['en' => ['name' => 'Default limit']], 'resolvement' => 'entries', 'type' => 'iframe', 'rateLimitPerHour' => null,
    ], formActor());

    expect($form->rate_limit_per_hour)->toBe(10);
});

test('a registered custom handler instance is resolved', function (): void {
    $form = Form::factory()->create(['handle' => 'instance-handler']);
    $handlers = app(FormHandlerRegistry::class);
    $handlers->clear();
    $handler = new RecordingFormHandler;
    $handlers->register($form->handle, $handler);

    expect((new CustomFormRegistry(app(), $handlers))->resolve($form))->toBe($handler);
    $handlers->clear();
});

test('reclassifying an entry moves it between the submission and spam counters', function (): void {
    $form = Form::factory()->create(['submissions_count' => 1, 'spam_count' => 1]);
    $legitimate = FormEntry::factory()->for($form)->create(['is_spam' => false]);
    $spam = FormEntry::factory()->for($form)->create(['is_spam' => true]);
    $entries = app(FormEntries::class);

    $entries->markLegitimate($spam, formActor());
    expect($form->fresh()->submissions_count)->toBe(2)->and($form->fresh()->spam_count)->toBe(0);

    $entries->delete($spam->id, formActor());
    expect($form->fresh()->submissions_count)->toBe(1);

    $entries->markSpam($legitimate, actor: formActor());
    expect($form->fresh()->submissions_count)->toBe(0)->and($form->fresh()->spam_count)->toBe(1);
});

test('the doctor inspects the configured package connection', function (): void {
    config(['database.connections.forms_empty' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    config(['nvl-forms.connection' => 'forms_empty']);

    $check = collect(app(FormsDoctor::class)->inspect())->firstWhere('key', 'schema.table.nvl_forms_forms');

    expect($check)->not->toBeNull()->and($check['passed'])->toBeFalse();
});

test('equivalent text forms of one address share a rate limit', function (): void {
    $form = Form::factory()->create(['require_csrf' => false, 'enable_honeypot' => false, 'enable_rate_limiting' => true, 'rate_limit_per_hour' => 1, 'restrict_public_access' => false]);
    $service = app(FormSubmissions::class);

    $entry = $service->submit($form, ['body' => 'First enquiry.'], ['origin' => 'https://example.com', 'ipAddress' => '2001:DB8:0:0:0:0:0:1']);

    expect($entry->ip_address)->toBe('2001:db8::1');
    expect(fn () => $service->submit($form, ['body' => 'Second enquiry.'], ['origin' => 'https://example.com', 'ipAddress' => '2001:db8::1']))
        ->toThrow(FormSubmissionRejectionException::class);
    expect(FormRateLimit::query()->where('form_id', $form->id)->count())->toBe(1)
        ->and(FormEntry::query()->where('form_id', $form->id)->count())->toBe(1);
});

test('repeated registration probes consume the rate limit', function (): void {
    $form = Form::factory()->create(['require_csrf' => false, 'enable_honeypot' => false, 'enable_rate_limiting' => true, 'rate_limit_per_hour' => 1, 'restrict_public_access' => false, 'allow_multiple_registrations' => false]);
    $service = app(FormSubmissions::class);
    $service->submit($form, ['email' => 'member@example.com'], ['origin' => 'https://example.com', 'ipAddress' => '192.0.2.80']);
    $probe = fn () => $service->submit($form, ['email' => 'member@example.com'], ['origin' => 'https://example.com', 'ipAddress' => '192.0.2.81']);

    expect($probe)->toThrow(fn (FormSubmissionRejectionException $exception) => expect($exception->statusCode())->toBe(409));
    expect($probe)->toThrow(fn (FormSubmissionRejectionException $exception) => expect($exception->statusCode())->toBe(429));
    expect(FormEntry::query()->where('form_id', $form->id)->count())->toBe(1);
});

test('the published configuration names the package connection and every weight the detector reads', function (): void {
    $published = require dirname(__DIR__, 3).'/config/nvl-forms.php';

    expect($published)->toHaveKey('connection')
        ->and($published['connection'])->toBeNull()
        ->and($published['security']['spam_protection']['score_weights'])->toHaveKey('suspicious_user_agent');
});

test('form search treats wildcard characters in the term literally', function (): void {
    Form::factory()->create(['handle' => 'summer_sale', 'name' => '100% off']);
    Form::factory()->create(['handle' => 'summerxsale', 'name' => '100 days']);
    $reader = app(FormReader::class);

    expect($reader->suggestions('summer_sale')->pluck('handle')->all())->toBe(['summer_sale'])
        ->and($reader->options(['q' => 'SUMMER_sale'])->pluck('sublabel')->all())->toBe(['summer_sale'])
        ->and($reader->paginate(filters: ['search' => '100%'], actor: formActor())->pluck('handle')->all())->toBe(['summer_sale'])
        ->and($reader->suggestions('summer')->count())->toBe(2);
});

test('public submissions refuse text no supported database can store', function (array $fields): void {
    $form = Form::factory()->create(['require_csrf' => false, 'enable_honeypot' => false, 'enable_rate_limiting' => false, 'restrict_public_access' => false]);

    expect(fn () => app(FormSubmissions::class)->submit($form, $fields, ['origin' => 'https://example.com', 'ipAddress' => '192.0.2.90']))
        ->toThrow(ValidationException::class);
    expect(FormEntry::query()->where('form_id', $form->id)->count())->toBe(0);
})->with([
    'malformed UTF-8' => [['body' => "caf\xE9"]],
    'NUL byte' => [['subject' => "quiet\0subject"]],
]);

test('public submissions refuse an origin host longer than a host name can be', function (): void {
    $form = Form::factory()->create(['require_csrf' => false, 'enable_honeypot' => false, 'enable_rate_limiting' => false, 'restrict_public_access' => false]);
    $origin = 'https://'.implode('.', array_fill(0, 6, str_repeat('a', 50))).'.example.com';

    expect(fn () => app(FormSubmissions::class)->submit($form, ['body' => 'A normal enquiry.'], ['origin' => $origin, 'ipAddress' => '192.0.2.91']))
        ->toThrow(ValidationException::class);
    expect(FormEntry::query()->where('form_id', $form->id)->count())->toBe(0);
});

test('a malformed user agent is stored as valid text instead of failing the submission', function (): void {
    $form = Form::factory()->create(['require_csrf' => false, 'enable_honeypot' => false, 'enable_rate_limiting' => false, 'restrict_public_access' => false]);

    $entry = app(FormSubmissions::class)->submit($form, ['body' => 'A normal enquiry.'], ['origin' => 'https://example.com', 'ipAddress' => '192.0.2.92', 'userAgent' => "curl\xE9/8.0"]);

    expect($entry->user_agent)->toBe('curl?/8.0')
        ->and($entry->getSecurityFlag('suspicious_user_agent'))->toBe('curl?/8.0');
});

test('a session that holds no token fails the CSRF check instead of crashing', function (): void {
    $form = Form::factory()->create(['require_csrf' => true, 'enable_honeypot' => false, 'enable_rate_limiting' => false, 'restrict_public_access' => false]);
    $session = new Store('forms', new ArraySessionHandler(120));

    expect(fn () => app(FormSubmissions::class)->submit($form, ['body' => 'A normal enquiry.'], ['origin' => 'https://example.com', 'ipAddress' => '192.0.2.93', 'session' => $session, 'csrfToken' => 'client-token']))
        ->toThrow(fn (FormSubmissionRejectionException $exception) => expect($exception->statusCode())->toBe(419));
});
