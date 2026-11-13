<?php

declare(strict_types=1);
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Nvl\Forms\Contracts\FormSpamDetector;
use Nvl\Forms\Enums\Resolvement;
use Nvl\Forms\Exceptions\FormException;
use Nvl\Forms\Exceptions\FormSubmissionRejectionException;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormAnalytic;
use Nvl\Forms\Models\FormEntry;
use Nvl\Forms\Models\FormRateLimit;
use Nvl\Forms\Models\FormSubmissionReceipt;
use Nvl\Forms\Services\EntryCallbackRegistry;
use Nvl\Forms\Services\FormSubmissions;
use Nvl\Forms\Support\FormHandlerRegistry;
use Nvl\Forms\Tests\Fixtures\Consumer\Data\Mutations\SubmitFormPayload;

test('handle public form submission creates entry for entries resolvement', function (): void {
    $form = Form::factory()->create(['resolvement' => Resolvement::ENTRIES, 'enable_honeypot' => false, 'enable_rate_limiting' => false, 'restrict_public_access' => false, 'require_csrf' => false, 'submissions_count' => 0]);
    $data = SubmitFormPayload::validatedInput(['firstName' => 'John', 'lastName' => 'Doe', 'email' => 'john@example.com', 'body' => 'Hello, this is a test submission.', 'submittedFrom' => 'https://example.com']);
    $request = Request::create('/submit', 'POST');
    $request->server->set('REMOTE_ADDR', '10.0.0.1');
    $result = app(FormSubmissions::class)->submit($form, formNativeInput($data), formNativeInput(formSubmissionContext($request)));
    expect($result)->toBeInstanceOf(FormEntry::class)->and(formSubmissionId($result))->not->toBeEmpty()->and($result->form->id)->toBe($form->id);
    $this->assertDatabaseHas(FormEntry::query()->getModel()->getTable(), ['form_id' => $form->id, 'email' => 'john@example.com']);
});
test('handle public form submission increments counters', function (): void {
    $form = Form::factory()->create(['resolvement' => Resolvement::ENTRIES, 'enable_honeypot' => false, 'enable_rate_limiting' => false, 'restrict_public_access' => false, 'require_csrf' => false, 'submissions_count' => 0, 'views_count' => 0]);
    $data = SubmitFormPayload::validatedInput(['email' => 'counter@example.com', 'submittedFrom' => 'https://example.com']);
    $request = Request::create('/submit', 'POST');
    $request->server->set('REMOTE_ADDR', '10.0.0.2');
    app(FormSubmissions::class)->submit($form, formNativeInput($data), formNativeInput(formSubmissionContext($request)));
    expect($form->fresh()->submissions_count)->toBe(1);
});
test('handle public form submission delegates to custom handler', function (): void {
    $form = Form::factory()->create(['resolvement' => Resolvement::CUSTOM, 'handle' => 'test-custom-handle', 'enable_honeypot' => false, 'enable_rate_limiting' => false, 'restrict_public_access' => false, 'require_csrf' => false]);
    // Register a callable handler — CustomFormRegistry resolves callables via CallbackFormHandler
    $registry = app(FormHandlerRegistry::class);
    $registry->register('test-custom-handle', function (Form $form, array $data, array $context): array {
        return ['entry_id' => 'custom-123', 'meta' => ['handled' => true]];
    });
    $data = SubmitFormPayload::validatedInput(['email' => 'custom@example.com', 'submittedFrom' => 'https://example.com']);
    $request = Request::create('/submit', 'POST');
    $request->server->set('REMOTE_ADDR', '10.0.0.3');
    $result = app(FormSubmissions::class)->submit($form, formNativeInput($data), formNativeInput(formSubmissionContext($request)));
    expect($result)->toBeInstanceOf(FormSubmissionReceipt::class)->and(formSubmissionId($result))->toBe('custom-123')->and($result->hasBookkeepingWarning())->toBeFalse();
});
test('handle public form submission executes custom handler outside a forms-owned transaction', function (): void {
    $form = Form::factory()->create(['resolvement' => Resolvement::CUSTOM, 'handle' => 'custom-no-outer-transaction', 'enable_honeypot' => false, 'enable_rate_limiting' => false, 'restrict_public_access' => false, 'require_csrf' => false]);
    $observedTransactionLevel = null;
    $registry = app(FormHandlerRegistry::class);
    $registry->register('custom-no-outer-transaction', function (Form $form, array $data, array $context) use (&$observedTransactionLevel): array {
        $observedTransactionLevel = DB::transactionLevel();

        return ['entry_id' => 'custom-no-outer-transaction'];
    });
    $data = SubmitFormPayload::validatedInput(['email' => 'custom@example.com', 'submittedFrom' => 'https://example.com']);
    $request = Request::create('/submit', 'POST');
    $request->server->set('REMOTE_ADDR', '10.0.0.4');
    $result = app(FormSubmissions::class)->submit($form, formNativeInput($data), formNativeInput(formSubmissionContext($request)));
    expect(formSubmissionId($result))->toBe('custom-no-outer-transaction')->and($observedTransactionLevel)->toBe(0);
});
test('custom submissions reject an open source transaction before consuming attempts or invoking the handler', function (bool $configuredConnection): void {
    if ($configuredConnection) {
        $sourceName = config('database.default');
        config(['database.connections.unrelated_submission' => config('database.connections.'.$sourceName), 'nvl-forms.connection' => $sourceName, 'database.default' => 'unrelated_submission']);
    }
    $form = Form::factory()->create(['handle' => 'custom-transaction-boundary', 'resolvement' => Resolvement::CUSTOM, 'allow_multiple_registrations' => true, 'restrict_public_access' => false, 'enable_rate_limiting' => true, 'enable_honeypot' => false, 'require_csrf' => false]);
    $handlerCalls = 0;
    app(FormHandlerRegistry::class)->register($form->handle, function () use (&$handlerCalls): array {
        $handlerCalls++;

        return ['entry_id' => 'external-'.$handlerCalls];
    });
    $source = $form->getConnection();
    $fields = ['email' => 'transaction@example.com'];
    $protection = ['origin' => 'https://example.test', 'ipAddress' => '127.0.0.1', 'userAgent' => 'Mozilla/5.0', 'idempotency_key' => 'custom-transaction-key'];
    $source->beginTransaction();
    try {
        expect(fn () => app(FormSubmissions::class)->submit($form, $fields, $protection))->toThrow(FormException::class);
        expect($handlerCalls)->toBe(0)
            ->and(FormSubmissionReceipt::query()->count())->toBe(0)
            ->and(FormRateLimit::query()->count())->toBe(0)
            ->and(FormAnalytic::query()->count())->toBe(0);
    } finally {
        $source->rollBack();
    }
    $receipt = app(FormSubmissions::class)->submit($form, $fields, $protection);
    $replay = app(FormSubmissions::class)->submit($form, $fields, $protection);
    expect($receipt)->toBeInstanceOf(FormSubmissionReceipt::class)
        ->and($replay->id)->toBe($receipt->id)
        ->and($receipt->result_id)->toBe('external-1')
        ->and($handlerCalls)->toBe(1);
    $source->beginTransaction();
    try {
        expect(fn () => app(FormSubmissions::class)->submit($form, $fields, $protection))->toThrow(FormException::class);
    } finally {
        $source->rollBack();
    }
    expect(FormSubmissionReceipt::query()->findOrFail($receipt->id)->result_id)->toBe('external-1')
        ->and($handlerCalls)->toBe(1);
})->with(['default source' => false, 'configured source' => true]);

test('custom submissions remain durable when only an unrelated connection transaction rolls back', function (): void {
    $sourceName = config('database.default');
    config(['database.connections.unrelated_submission' => config('database.connections.'.$sourceName), 'nvl-forms.connection' => $sourceName, 'database.default' => 'unrelated_submission']);
    $form = Form::factory()->create(['handle' => 'custom-unrelated-transaction', 'resolvement' => Resolvement::CUSTOM, 'allow_multiple_registrations' => true, 'restrict_public_access' => false, 'enable_rate_limiting' => false, 'enable_honeypot' => false, 'require_csrf' => false]);
    $handlerCalls = 0;
    app(FormHandlerRegistry::class)->register($form->handle, function () use (&$handlerCalls): array {
        $handlerCalls++;

        return ['entry_id' => 'durable-external-result'];
    });
    $fields = ['email' => 'transaction@example.com'];
    $protection = ['origin' => 'https://example.test', 'ipAddress' => '127.0.0.1', 'userAgent' => 'Mozilla/5.0', 'idempotency_key' => 'custom-unrelated-key'];
    DB::beginTransaction();
    try {
        $receipt = app(FormSubmissions::class)->submit($form, $fields, $protection);
    } finally {
        DB::rollBack();
    }
    $replay = app(FormSubmissions::class)->submit($form, $fields, $protection);
    expect($replay->id)->toBe($receipt->id)
        ->and($replay->result_id)->toBe('durable-external-result')
        ->and($handlerCalls)->toBe(1);
});
test('handle public form submission preserves custom handler success when forms bookkeeping fails', function (): void {
    $form = Form::factory()->create(['resolvement' => Resolvement::CUSTOM, 'handle' => 'custom-bookkeeping-failure', 'enable_honeypot' => false, 'enable_rate_limiting' => false, 'restrict_public_access' => false, 'require_csrf' => false]);
    $registry = app(FormHandlerRegistry::class);
    $registry->register('custom-bookkeeping-failure', function (Form $form, array $data, array $context): array {
        return ['entry_id' => 'custom-bookkeeping-failure'];
    });
    Form::updating(static function (): never {
        throw new RuntimeException('Forms bookkeeping failed');
    });
    $data = SubmitFormPayload::validatedInput(['email' => 'custom@example.com', 'submittedFrom' => 'https://example.com']);
    $request = Request::create('/submit', 'POST');
    $request->server->set('REMOTE_ADDR', '10.0.0.5');
    $result = app(FormSubmissions::class)->submit($form, formNativeInput($data), formNativeInput(formSubmissionContext($request)));
    expect(formSubmissionId($result))->toBe('custom-bookkeeping-failure')->and($result->hasBookkeepingWarning())->toBeTrue();
});
test('handle public form submission throws when custom handler not registered', function (): void {
    $form = Form::factory()->create(['resolvement' => Resolvement::CUSTOM, 'handle' => 'nonexistent-handle', 'require_csrf' => false]);
    $data = SubmitFormPayload::validatedInput(['email' => 'test@example.com']);
    $request = Request::create('/submit', 'POST');
    expect(fn () => app(FormSubmissions::class)->submit($form, formNativeInput($data), formNativeInput(formSubmissionContext($request))))->toThrow(FormSubmissionRejectionException::class);
});
test('entry callbacks wait for the surrounding transaction and disappear on rollback', function (bool $commit): void {
    $form = Form::factory()->create(['handle' => 'transactional-callback', 'resolvement' => Resolvement::ENTRIES, 'enable_honeypot' => false, 'enable_rate_limiting' => false, 'restrict_public_access' => false, 'require_csrf' => false]);
    $observedEntries = [];
    app(EntryCallbackRegistry::class)->register($form->handle, function (Form $form, FormEntry $entry) use (&$observedEntries): void {
        $observedEntries[] = FormEntry::query()->findOrFail($entry->id)->id;
    });
    DB::beginTransaction();
    try {
        $result = app(FormSubmissions::class)->submit($form, formNativeInput(SubmitFormPayload::validatedInput(['email' => 'transaction@example.com'])), formNativeInput(formSubmissionContext(Request::create('/submit', 'POST'))));
        expect($observedEntries)->toBe([]);
    } catch (Throwable $exception) {
        DB::rollBack();
        throw $exception;
    }
    if ($commit) {
        DB::commit();
        expect($observedEntries)->toBe([formSubmissionId($result)]);
    } else {
        DB::rollBack();
        expect($observedEntries)->toBe([])->and(FormEntry::query()->whereKey(formSubmissionId($result))->exists())->toBeFalse();
    }
})->with(['commit' => true, 'rollback' => false]);
test('public submission honors an interface-only configured spam detector', function (Resolvement $resolvement, bool $honeypot): void {
    $form = Form::factory()->create(['handle' => 'configured-detector', 'resolvement' => $resolvement, 'enable_honeypot' => true, 'enable_rate_limiting' => false, 'restrict_public_access' => false, 'require_csrf' => false]);
    app()->instance(FormSpamDetector::class, new class($honeypot) implements FormSpamDetector
    {
        public function __construct(private readonly bool $honeypot) {}

        public function checkHoneypot(Form $form, array $data): bool
        {
            return $this->honeypot;
        }

        public function calculateSpamScore(Form $form, array $data, string $ipAddress, ?string $userAgent = null, ?FormRateLimit $rateLimit = null, ?float $formLoadTime = null): float
        {
            return 65;
        }

        public function shouldBlockSubmission(float $spamScore): bool
        {
            return $spamScore >= 60;
        }

        public function shouldFlagSubmission(float $spamScore): bool
        {
            return $spamScore >= 20 && $spamScore < 60;
        }
    });
    $handlerCalls = 0;
    app(FormHandlerRegistry::class)->register($form->handle, function () use (&$handlerCalls): array {
        $handlerCalls++;

        return ['entry_id' => 'unexpected-handler-result'];
    });
    $submit = fn () => app(FormSubmissions::class)->submit($form, formNativeInput(SubmitFormPayload::validatedInput(['email' => 'safe@example.com', 'body' => 'Hello'])), formNativeInput(formSubmissionContext(Request::create('/submit', 'POST', server: ['HTTP_USER_AGENT' => 'Mozilla/5.0']))));
    if ($honeypot || $resolvement === Resolvement::CUSTOM) {
        expect($submit)->toThrow(Exception::class);
        expect(FormEntry::query()->count())->toBe(0);
    } else {
        $result = $submit();
        $entry = FormEntry::query()->findOrFail(formSubmissionId($result));
        expect($entry->is_spam)->toBeTrue()->and($entry->spam_score)->toBe(65);
    }
    expect($handlerCalls)->toBe(0);
})->with(['entry scoring' => [Resolvement::ENTRIES, false], 'custom scoring' => [Resolvement::CUSTOM, false], 'entry honeypot' => [Resolvement::ENTRIES, true], 'custom honeypot' => [Resolvement::CUSTOM, true]]);
