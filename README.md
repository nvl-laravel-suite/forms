# NVL Forms

Forms provides native Laravel services for localized definitions, protected submissions, durable custom receipts, entry privacy, analytics and optional native ownership. Requires PHP 8.4 and Laravel 13.

## Native workflows

Resolve these concrete services through Laravel's container:

- `FormLifecycle::create($attributes, $actor)`, `update($form, $attributes, $actor)`, `duplicate($form, $newName, $actor)` and `delete($form, $actor)` return models or deletion status. Updates require `expectedRevision`; `translationMode` is `patch` or `replace`. Translations retain arbitrary nested content by locale; duplicates reset counters.
- `FormReader::get($form)` resolves public forms; `schema($identifier)` returns native arrays. `details`, `paginate`, `search`, `analytics` and `summary` accept explicit management actors. `options` and `suggestions` return native collections or arrays.
- `FormSubmissions::submit(Form|string $formIdentifier, array $fields, array $protection): FormEntry|FormSubmissionReceipt` performs the complete submission workflow. Call this entry point for public submissions.
- `FormEntries` owns `get`, `paginate`, `update`, `delete`, `redact`, `anonymize`, `flag`, `markSpam`, `markLegitimate` and `export`. Supply the authorized actor; export returns its generated file path for consumer delivery.
- `FormAnalytics` retains submission/view/origin/rate bookkeeping. Existing spam, rate-limit and extension ports remain injectable.

Management defaults to denial. Configure `nvl-forms.authorization.gate` and pass an authorized native `Authenticatable`; services never resolve an ambient authenticated user.

## Public submissions

`fields` accepts subject, firstName, lastName, email, phone, address, body and bounded submissionData. `protection` requires a canonical HTTP origin and ipAddress; optional keys are userAgent, sessionId, native session, csrfToken, publicToken, idempotencyKey, publicSite, native actor and locale. Unknown protection keys are rejected. The consumer extracts these values from its transport.

The service preserves availability, allowed-origin checks, signed public-token expiry and form/site binding, session CSRF, rate consumption, honeypot, server-verified timing, spam accounting, registration uniqueness and payload-bound idempotency. Never pass client-selected owner authority or trusted timing. CORS/header/HTTP throttle/redirect/rendering behavior belongs to the consumer; active examples live under `tests/Fixtures/Consumer`.

The result type is the discriminant: ordinary submissions return `FormEntry`; custom handlers return the existing durable `FormSubmissionReceipt`, whose `result_id` is the external handler's result identifier. Custom results do not create a synthetic FormEntry. Receipts preserve replay, registration claims and failure state; `hasBookkeepingWarning()` indicates successful downstream work whose ancillary accounting failed.

Custom handlers implement `CustomFormHandler::handle(Form $form, array $data, array $context): array`; their result contains `entry_id`. Entry callbacks receive explicit arrays. Committed callbacks run after the actual source outer commit, never after rollback or idempotent replay. They are local callbacks, not a crash-durable outbox.

## Optional native ownership

The default `Form` remains unscoped. Apps without ownership do not need
`nvl/ownership`, NVL Auth or NVL Tenancy. To opt in, extend the package model,
retain its native table, UUID, casts and guarded persistence methods, and configure
`nvl-forms.models.form` to that exact class. Every service, relationship,
translation registration and shipped root factory resolves that class.

```php
use Nvl\Forms\Models\Form as BaseForm;
use Nvl\Support\Ownership\ValueObjects\OwnershipDefinition;

class Form extends BaseForm
{
    public static function ownershipDefinition(): OwnershipDefinition
    {
        return OwnershipDefinition::column(Company::class, 'company_id');
    }
}
```

Create the native `company_id` FK yourself, or return
`OwnershipDefinition::pivot(Company::class, 'company_forms_ownership',
'form_id', 'company_id')` and create that concrete exclusive-primary pivot
with native FKs and one primary assignment per record. The base already supplies
`Ownable`/`HasOwnership`; a consumer does not add a second ownership scope.
Inherited child records retain their canonical parent and cannot be reassigned.
Existing records require explicit reviewed per-record mappings through the
[Ownership adoption coordinator](https://github.com/nvl-laravel-suite/ownership#readme);
selecting a subclass or enabling the runtime never adopts storage.

Form handles become owner-local; entries, origins, translations, rates, analytics
and custom submission receipts inherit the configured Form. Completed receipts
retain their original IDs, retry keys, digests and results during adoption.

Public routes require a host binding of `Nvl\Ownership\Contracts\OwnerSiteResolver`.
Resolve only an enabled persisted origin mapping and return
`VerifiedOwnerSiteContext(owner: $canonicalReference, site: 'storefront',
canonicalOrigin: 'https://example.test', resources: $explicitResourceKeys)`.
The consumer calls `PublicOwnerSiteRunner::run($origin, $callback)`, which revalidates the canonical owner and exact request origin before
bindings, caching and public work. Request query/header owner/site selections
never choose the partition. Include every read dependency in the graph; public
admission grants neither actor/system/central entry nor queue capture.

Include `forms.forms`, `forms.translations`, `forms.origins`, `forms.entries`,
`forms.receipts`, `forms.rates` and `forms.analytics` in the public graph. Grant only
submission children in `writableResources`; ordinary Form management writes remain
denied. Package bookkeeping uses a fixed counter-only native capability. Public
tokens use version 3 and bind the full owner, site, canonical origin and activation
generations; legacy tenant/unowned tokens cannot authorize an owned form.

Use `EntryCallbackRegistry::registerOwnership('contact', ContactSubmitted::class)`
with `OwnershipEntrySubmissionCallback::after(Form $form, FormEntry $entry,
array $context)`. The immutable context contains
owner/site/origin/locale/correlation facts. Callbacks resolve freshly after the
actual source commits, revalidate the original site/generation and receive fresh
canonical models. Rollbacks and idempotent replays do not deliver callbacks.
Custom handlers and render providers in owned mode must be named classes;
legacy Request-dependent entry callbacks must migrate before activation. All extension contexts are explicit arrays; HTTP extraction belongs to consumers.


## Configuration and migrations

Publish the package's normal configuration and migrations through Laravel. Configure the Form subclass at `nvl-forms.models.form`. Released storage migrations remain intact; the deprecated Tenancy runtime and automatic bridge conversion are removed. No upgrade deletes consumer data.

## Events and verification

`FormChanged` and `FormEntryChanged` snapshot scalar identifiers and bounded operation facts after the source commit. Personal entry content and export paths are excluded. See `resources/event-catalog.json` and `docs/events.md`.

Run package Pest tests with its `phpunit.xml.dist`. Consumer HTTP/DTO fixtures remain active. `tests/Historical` preserves retired bridge, automatic old-schema conversion and generated Action-interface metadata tests outside active discovery; native Ownership, committed callback, privacy, submission and lifecycle tests remain active.
