<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Nvl\Forms\Actions\Form\HandlePublicFormSubmissionAction;
use Nvl\Forms\Contracts\FormRenderDataProvider;
use Nvl\Forms\Data\Mutations\SubmitFormPayload;
use Nvl\Forms\Exceptions\FormException;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormEntry;
use Nvl\Forms\Models\FormSubmissionReceipt;
use Nvl\Forms\Services\CustomFormRegistry;
use Nvl\Forms\Services\CustomSubmissionReceiptService;
use Nvl\Forms\Services\EntryCallbackRegistry;
use Nvl\Forms\Services\PublicFormTokenService;
use Nvl\Forms\Support\FormHandlerRegistry;
use Nvl\Forms\Support\FormRenderDataRegistry;
use Nvl\Forms\Tests\Fixtures\Company;
use Nvl\Forms\Tests\Fixtures\OwnedEntryCallback;
use Nvl\Forms\Tests\Fixtures\ThrowingOwnedEntryCallback;
use Nvl\Ownership\Contracts\OwnerSiteResolver;
use Nvl\Ownership\Services\OwnerIdentityResolver;
use Nvl\Ownership\Services\PublicOwnerSiteRunner;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;
use Nvl\Support\Ownership\ValueObjects\VerifiedOwnerSiteContext;

beforeEach(function (): void {
    $this->app->bind(OwnerSiteResolver::class, static fn () => new class implements OwnerSiteResolver
    {
        public function resolve(Request $request): ?VerifiedOwnerSiteContext
        {
            if (! in_array($request->getHost(), ['a.forms.test', 'b.forms.test', 'c.forms.test'], true)) {
                return null;
            }
            $resources = ['forms.forms', 'forms.entries', 'forms.receipts', 'forms.origins', 'forms.rates', 'forms.analytics', 'forms.translations'];

            return new VerifiedOwnerSiteContext(app(OwnerIdentityResolver::class)->reference(Company::findOrFail($request->getHost() === 'c.forms.test' ? 8 : 7)),
                $request->getHost() === 'b.forms.test' ? 'beta' : 'alpha', 'https://'.$request->getHost(), $resources,
                ['forms.entries', 'forms.receipts', 'forms.rates', 'forms.analytics', 'forms.origins']);
        }
    });
    $this->forms = [];
    foreach ([7, 8] as $id) {
        $this->forms[$id] = $this->owned($id, fn () => $this->createOwnedForm());
    }
});

it('binds versioned public tokens to full owner identity site canonical origin and active generation', function (): void {
    $runner = app(PublicOwnerSiteRunner::class);
    $form = $this->forms[7];
    $token = $runner->run(Request::create('https://a.forms.test'), fn () => app(PublicFormTokenService::class)->issue($form, now()->addMinute(), 'alpha'));
    expect($runner->run(Request::create('https://a.forms.test'), fn () => app(PublicFormTokenService::class)->validate($token, $form, 'alpha')))->toBeTrue()
        ->and($runner->run(Request::create('https://b.forms.test'), fn () => app(PublicFormTokenService::class)->validate($token, $form, 'beta')))->toBeFalse()
        ->and($runner->run(Request::create('https://b.forms.test'), fn () => app(PublicFormTokenService::class)->validateForHandle($token, 'contact')))->toBeFalse()
        ->and($runner->run(Request::create('https://c.forms.test'), fn () => app(PublicFormTokenService::class)->validate($token, $this->forms[8], 'alpha')))->toBeFalse();
});

it('admits the public host before handle binding token issuance and submission middleware', function (): void {
    foreach (['a' => 7, 'c' => 8] as $host => $id) {
        $this->getJson('https://'.$host.'.forms.test/nvl/api/v1/forms/contact/render?owner=8&site=foreign')
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('data.id', $this->forms[$id]->id)
            ->assertJsonStructure(['public_token']);
    }
    $this->getJson('https://unknown.forms.test/nvl/api/v1/forms/contact/render')->assertNotFound();
});

it('keeps durable custom receipts owner local and canonically denies foreign replay claims', function (): void {
    $claims = [];
    foreach ([7, 8] as $id) {
        $claims[$id] = $this->owned($id, fn () => app(CustomSubmissionReceiptService::class)->claim($this->forms[$id], ['email' => 'same@example.test'], 'same-session', 'same-key'));
    }
    $this->owned(7, function () use ($claims): void {
        expect(FormSubmissionReceipt::count())->toBe(1);
        expect(fn () => app(CustomSubmissionReceiptService::class)->complete($claims[8], 'foreign'))->toThrow(OwnershipViolation::class);
    });
});

it('delivers class-resolved callbacks only after native commit with immutable public owner facts', function (): void {
    OwnedEntryCallback::$calls = [];
    app(EntryCallbackRegistry::class)->registerOwnership('contact', OwnedEntryCallback::class);
    $runner = app(PublicOwnerSiteRunner::class);
    $runner->run(Request::create('https://a.forms.test'), function (): void {
        $form = $this->forms[7];
        $request = Request::create('https://a.forms.test/submit', 'POST');
        $request->headers->set('Idempotency-Key', 'same-public-key');
        $request->headers->set('Origin', 'https://a.forms.test');
        $context = formSubmissionContext($request);
        $data = SubmitFormPayload::from(['email' => 'callback@example.test', 'body' => 'A committed submission']);
        $connection = $form->getConnection();
        $connection->beginTransaction();
        $first = app(HandlePublicFormSubmissionAction::class)->execute($form, $data, $context);
        expect(OwnedEntryCallback::$calls)->toBe([]);
        $connection->commit();
        expect(OwnedEntryCallback::$calls)->toBe([['7', 'alpha', $first->entryId]]);
        $replay = app(HandlePublicFormSubmissionAction::class)->execute($form, $data, $context);
        expect($replay->entryId)->toBe($first->entryId)->and(OwnedEntryCallback::$calls)->toHaveCount(1);
    });
});

it('rejects forged Form storage before token validation and timing facts are trusted', function (): void {
    app(PublicOwnerSiteRunner::class)->run(Request::create('https://a.forms.test'), function (): void {
        $form = $this->forms[7];
        $tokens = app(PublicFormTokenService::class);
        $token = $tokens->issue($form, now()->addMinute(), 'alpha');
        $forged = clone $form;
        config(['database.connections.foreign_form_storage' => config('database.connections.sqlite')]);
        $forged->setConnection('foreign_form_storage');
        expect($tokens->validate($token, $forged, 'alpha'))->toBeFalse()
            ->and($tokens->issuedAt($token, $forged, 'alpha'))->toBeNull();
    });
});

it('rejects retained public extension objects and legacy closures in an owned graph', function (): void {
    $renders = app(FormRenderDataRegistry::class);
    $renders->register('contact', new class implements FormRenderDataProvider
    {
        public function getData(Form $form, Request $request): array
        {
            return ['foreign_cached_value' => 'private'];
        }

        public function getTranslations(Form $form): array
        {
            return [];
        }
    });
    app(FormHandlerRegistry::class)->register('contact', fn () => ['entry_id' => 'retained-handler']);
    app(PublicOwnerSiteRunner::class)->run(Request::create('https://a.forms.test'), function () use ($renders): void {
        expect(fn () => $renders->getData($this->forms[7], Request::create('/')))->toThrow(FormException::class)
            ->and(fn () => app(CustomFormRegistry::class)->resolve($this->forms[7]))->toThrow(FormException::class);
    });
});

it('preserves committed submission success and later callbacks after an extension fails', function (): void {
    OwnedEntryCallback::$calls = [];
    app(EntryCallbackRegistry::class)->registerOwnership('contact', ThrowingOwnedEntryCallback::class);
    app(EntryCallbackRegistry::class)->registerOwnership('contact', OwnedEntryCallback::class);
    app(PublicOwnerSiteRunner::class)->run(Request::create('https://a.forms.test'), function (): void {
        $request = Request::create('https://a.forms.test/submit', 'POST');
        $request->headers->set('Origin', 'https://a.forms.test');
        $request->headers->set('Idempotency-Key', 'callback-failure-proof');
        $result = app(HandlePublicFormSubmissionAction::class)->execute($this->forms[7], SubmitFormPayload::from(['email' => 'durable@example.test', 'body' => 'Committed']), formSubmissionContext($request));
        expect(OwnedEntryCallback::$calls)->toBe([['7', 'alpha', $result->entryId]]);
        expect(FormEntry::whereKey($result->entryId)->exists())->toBeTrue();
    });
});
