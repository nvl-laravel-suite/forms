# NVL Forms

Forms provides native Laravel services for localized definitions, protected submissions, durable custom receipts, entry privacy, analytics and optional native ownership. Requires PHP 8.4 and Laravel 13.

Management services and policies reload canonical Eloquent actors before host authorization. Caller-modified attributes and loaded relations cannot supply privileges; non-Eloquent providers retain their own identity and freshness contract.

## Native workflows

Resolve these concrete services through Laravel's container:

- `FormLifecycle::create($attributes, $actor)`, `update($form, $attributes, $actor)`, `duplicate($form, $newName, $actor)` and `delete($form, $actor)` return models or deletion status. Updates require `expected_revision`; `translation_mode` is `patch` or `replace`. Translations retain arbitrary nested content by locale; patch updates preserve omitted copy and content keys, and explicit null clears nullable copy. Unknown and protected mutation keys are rejected. Duplicates reset counters.
- `FormReader::get($form)` resolves public forms; `schema($identifier)` returns native arrays. `details`, `paginate`, `search`, `analytics` and `summary` accept explicit management actors. `options` and `suggestions` return native collections or arrays.
- `FormSubmissions::submit(Form|string $formIdentifier, array $fields, array $protection): FormEntry|FormSubmissionReceipt` performs the complete submission workflow. Call this entry point for public submissions.
- `FormEntries` owns `get`, `paginate`, `update`, `delete`, `redact`, `anonymize`, `flag`, `markSpam`, `markLegitimate` and `export`. Supply the authorized actor; export returns its generated file path for consumer delivery.
- `FormAnalytics` retains submission/view/origin/rate bookkeeping. Existing spam, rate-limit and extension ports remain injectable.

Bind `FormRateLimiter` and `FormSpamDetector` before Forms registration to replace the native security adapters. Conditional defaults preserve those bindings and retain request/job scoped lifetimes.

Management defaults to denial. Configure `nvl-forms.authorization.gate` and pass an authorized native `Authenticatable`; services never resolve an ambient authenticated user.

## Public submissions

`fields` accepts subject, firstName, lastName, email, phone, address, body and bounded submissionData. Payload byte, depth and item limits apply to the serialized JSON representation, including nested objects and `JsonSerializable` values, on both submissions and entry updates. Unknown or protected root fields are rejected. `protection` requires a canonical HTTP origin and ipAddress; optional keys are userAgent, sessionId, native session, csrfToken, publicToken, idempotency_key, publicSite, native actor and locale. Unknown protection keys are rejected. The consumer extracts these values from its transport. An explicit origin port is part of the matched and stored host (`embed.example.test:8443`); an allowed origin without a port admits every port. The IP address is canonicalised before rate limiting and storage. Text fields must be valid UTF-8 without NUL bytes.

Lifecycle date patches retain omitted boundaries and validate the resulting availability window against the current form. Explicit null clears a boundary. Enabling date restrictions also validates the stored window; equal and open boundaries are supported.

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

## Bounded exports and explicit retention

`FormReader::details(..., recordView: true, actor: $actor)` is an authorized management read that also increments the view counter, inserts analytics and may update origin usage. Pass `recordView: false` for previews, polling and cacheable reads that should not count visits. Public `get()`/`schema()` do not implicitly record views, and the headless package registers no public GET route. If the host explicitly records public visits through `FormAnalytics::view`, apply host throttling and a deliberate tracking policy before allowing traffic to create telemetry rows.

`FormEntries::export($form, $options, $actor)` returns an absolute path on the configured local filesystem disk. Remote disks are unsupported by this path contract. It captures sanitized scalar cells in a private temporary snapshot, discovers the dynamic columns from those same cells, then streams CSV without rereading mutable submissions. Rows with unknown creation timestamps are exported last with an empty date; explicit date filters exclude them. Private directories include configured database/schema/table identity, Form and ownership scope. Current management/export authorization and native owner/source identity are checked before publication. Incomplete snapshots and artifacts are removed on failure; the export event still follows the actual source commit.

`nvl-forms.exports` defaults to `disk=local`, `maximum_rows=10000`, `maximum_dynamic_fields=250`, `maximum_bytes=67108864` and `chunk_size=200`. Overflow fails clearly instead of silently truncating the selected dataset. An explicit `limit` must fit the row budget. The byte budget applies to both private snapshot encoding and final CSV. `include_sensitive_data` controls Email, Phone, Address and IP Address columns; names, messages and arbitrary submission fields can still contain personal data. Disable `include_submission_data` when those dynamic fields must be omitted. Select private storage outside the public web root. The lower-level collection formatter still returns a complete PHP string; use the native export service for bounded storage output.

Exports do not implicitly delete old files. `FormRetention::prune($form, $actor, $dryRun)` is a separate opt-in operation returning `entries`, `analytics`, `rates`, `exports` counts and `has_more`. Configure `nvl-forms.retention.enabled=true`, a host `authorization_ability` Gate and at least one positive `entries_days`, `analytics_days`, `rates_days` or `exports_hours` age. Every age defaults to null and retention defaults off. The Gate receives the fresh actor, canonical Form and resource kind; missing authority or ages fail closed. Existing entry privacy (`retention`) and deletion/legal-hold policies remain authoritative.

Each call processes at most `maximum_records` matching database records or file candidates (default 1000), using database `batch_size` (default 200) and fixed cutoffs. A bounded extra lookahead per enabled resource identifies remaining work. Repeat when `has_more` is true. Recent records, live rate blocks, other Forms and other owners remain outside the selected scope. File discovery streams the selected directory; cleanup recognizes only package export artifacts in that Form's private directory. Legacy flat exports and unknown files require reviewed host cleanup. Dry runs invoke policy without deleting data or files. Both preview and destructive retention reject host framework/native PDO transactions so the operation owns its database boundaries: each completed database record commits independently, and filesystem deletion is immediate. A later denial or source failure preserves already committed deletions and their real deletion facts; pending work is refused before using a changed source. Schedule retention outside host transactions under explicit admitted authority. The package installs no automatic retention schedule or system-principal bypass.

The existing low-level `FormRateLimiter::cleanupExpiredRecords()` remains an explicit, unscheduled host maintenance helper: it bulk-deletes old unblocked rate rows within the current ownership scope using `security.ip_blocking.cleanup_after_days` (default 7). That live setting also drives rate-statistics `cleanup_needed`; it does not enable or supply an age to `FormRetention`. Protect any host exposure of the legacy helper. Use `FormRetention` for selected-Form, actor-authorized, bounded cleanup with explicit ages.

## Events and verification

`FormChanged` and `FormEntryChanged` snapshot scalar identifiers and bounded operation facts after the source commit. Personal entry content and export paths are excluded. See `resources/event-catalog.json` and `docs/events.md`.

Tests and `phpunit.xml.dist` are not part of the distributed package; run package Pest tests from the source repository. Consumer HTTP/DTO fixtures remain active. The source repository's `tests/Historical` preserves retired bridge, automatic old-schema conversion and generated Action-interface metadata tests outside active discovery; native Ownership, committed callback, privacy, submission and lifecycle tests remain active.

Shared [service contracts, model extensions and composition](https://github.com/nvl-laravel-suite/laravel-suite/blob/main/docs/consumer-api.md) apply alongside the package-specific rules above.
