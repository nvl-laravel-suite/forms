# Forms integration recipes

Read [installation](installation.md) for package setup and [API](api.md) for full declarations.
The following `App\...` names are application-owned example adapters, not shipped classes.

## Management, model and extension setup

Set `nvl-forms.authorization.gate` to a host Gate ability and define that ability
for the explicit actor. The default is null and denies management. Services reload
Eloquent actors through Core, discarding caller-mutated roles, relations and storage.
A provider-owned non-Eloquent actor must have an identity. Public admission is denied
management even when its ordinary actor could pass a management Gate.

```php
config(['nvl-forms.authorization.gate' => 'manage-forms']);
Gate::define('manage-forms', fn ($actor): bool => $actor->can('manage-contact-forms'));
```

Use the application's real permission rule; do not turn this example into a blanket grant.
`FormEntryDeletionPolicy` and `FormEntryPrivacyPolicy` are separate host seams for
retention/deletion and redact/anonymize operations. `FormEntries::export` also checks
the actor's `export` ability on the Form. Transport permissions do not replace service policy.

Before package registration, bind custom `FormRateLimiter` / `FormSpamDetector`
implementations if needed; native `scopedIf` bindings preserve them. Resolve services
through the container so request/job scoping is retained.

Configure `nvl-forms.models.form` with a concrete `Form` subclass before provider
boot. Keep its native effective table/connection, UUID identity, casts, ownership
and lifecycle hooks. Add behavior with observers/bootable traits and call parent
implementations when extending inherited hooks. Related fixed models stay native.

## Lifecycle and localized form content

```php
use Nvl\Forms\Services\FormLifecycle;

$forms = app(FormLifecycle::class);
$form = $forms->create([
    'handle' => 'contact',
    'type' => 'iframe',
    'resolvement' => 'entries',
    'status' => 'draft',
    'translations' => ['en' => [
        'name' => 'Contact',
        'sections' => [['title' => 'Your message']],
    ]],
    'allowedOrigins' => ['https://www.example.com'],
], $manager);
$form = $forms->update($form, [
    'expected_revision' => $form->revision,
    'translations' => ['en' => ['successMessage' => 'Thank you']],
], $manager);
```

Lifecycle keys include `handle`, `translations`, `translation_mode`, `resolvement`,
`type`, `status`, `restrictPublicAccess`, `allowMultipleRegistrations`, `dateRestricted`,
`availableFrom`, `availableUntil`, `enableHoneypot`, `enableRateLimiting`,
`rateLimitPerHour`, `requireCsrf`, `corsSettings`, `options`, and `allowedOrigins`.
Update also requires `expected_revision`; ownership, actor and counter fields are protected.

Patch translations by default. `translation_mode='replace'` removes omitted locale
rows; keep arbitrary nested locale content intact when mapping a consumer DTO.
`allowedOrigins=null` clears the list. Omitted update attributes retain their values;
nullable attributes clear only where validation permits null. CORS settings are a
closed map: `policy`, `allowCredentials`, `allowWildcards`, `maxAge`, `allowedMethods`,
`allowedHeaders`. Preserve their allowed methods/header syntax and wildcard policy.

`duplicate($form, $newName, $manager)` resets lifecycle statistics and revision for
the new draft. Deletion rejects forms with entries. Native lifecycle writes return
fresh Form models with relevant translations/origins, rather than transport payloads.

## Public submission and retry

Resolve a form with `FormReader::get`; use `schema` for its native validation schema.
The consumer controls public render payloads and headers. Availability and all
submission protections are enforced by the complete submission operation.

```php
use Nvl\Forms\Services\FormSubmissions;

$result = app(FormSubmissions::class)->submit($form, [
    'email' => 'reader@example.com',
    'body' => 'Please contact me.',
    'submissionData' => ['topic' => 'support'],
], [
    'origin' => $verifiedOrigin,
    'ipAddress' => $clientIp,
    'session' => $session,
    'sessionId' => $session->getId(),
    'csrfToken' => $submittedCsrfToken,
    'publicToken' => $signedPublicToken,
    'idempotency_key' => $stableRetryKey,
    'locale' => 'en',
]);
```

The field allowlist is `subject`, `firstName`, `lastName`, `email`, `phone`,
`address`, `body`, `submissionData`. The last is bounded by configured bytes,
depth and item count. Unknown root keys, including actor/owner/revision claims,
are rejected. `submissionData` remains the heterogeneous consumer data area.

Protection accepts `origin`, `ipAddress`, `userAgent`, `sessionId`, `session`,
`csrfToken`, `publicToken`, `idempotency_key`, `publicSite`, `actor`, `locale`.
The host derives these facts from its transport; never copy an arbitrary request
map into protection. Origin must be a canonical HTTP(S) origin, not a path-bearing
referrer. Timing comes from a verified token, never `trustedFormLoadTime` input.
`PublicFormTokenService::issue($form, $expiresAt, $site)` takes an explicit expiry;
respect `nvl-forms.public.token_ttl_minutes` when choosing it in a host render flow.

Keep one retry key for the same form and payload. A matching replay returns the
existing entry/receipt; a different payload cannot reuse that key. Registration
uniqueness is a separate protection. Do not disable protections merely to simplify
an example or bypass public availability with a lower-level writer.

With native Ownership, run lookup/token/submission inside
`PublicOwnerSiteRunner::run($canonicalOrigin, $callback)`. The admitted owner, site,
canonical origin and active generation bind public tokens. Never infer admission
from `publicSite` or another submitted selector.

## Custom handlers and committed callbacks

Register `FormHandlerRegistry::register('contact', App\Forms\SendEnquiry::class)`
for a Form using `resolvement='custom'`. The class implements
`CustomFormHandler::handle(Form $form, array $data, array $context): array` and
returns `['entry_id' => $externalIdentifier, 'meta' => [...]]` when applicable.
`FormSubmissionReceipt::result_id` is the external identifier. Inspect the union
return type instead of treating it as a locally persisted FormEntry.

Custom submissions, including completed receipt replays, must start outside every
transaction on the Forms connection. Custom receipt claiming/completion is durable. A downstream external side effect
must have its own idempotency/recovery contract; the receipt is not a distributed
transaction. Successful custom work may carry a bookkeeping warning if counters fail.

Register normal committed callbacks with `EntryCallbackRegistry::register`.
Owned public flows use `registerOwnership($handle, $callbackClass)` and
`OwnershipEntrySubmissionCallback`; the package freshly constructs named classes
and re-enters verified original site/generation context. Callback context is an
array, not an HTTP Request. Delivery follows outer commit, and callback failure
cannot roll back the committed entry. Callbacks do not promise crash durability.

## Entries, analytics and safe delivery

`FormEntries` owns reads/listing, content updates, spam/legitimate changes, flags,
redaction, anonymization, export and deletion. Its update allowlist is `subject`,
`email`, `firstName`, `lastName`, `phone`, `address`, `body`, `submissionData`.
Redaction accepts only documented identifying content columns; anonymization
also clears security flags and marks the stored privacy timestamps. Preserve host
retention policy and counters; never replace these with direct model deletes.

`FormReader::details/paginate/search/analytics/summary` are management reads with
an explicit actor. `details(..., recordView: true, actor: $actor)` also writes a
view counter and analytics record, with optional origin usage; select
`recordView: false` for previews, polling or cacheable management reads.
Public `get`/`schema` do not implicitly track visits, and the package registers no
public GET route. Throttle and choose a deliberate tracking policy in the host
before invoking `FormAnalytics::view` for public traffic.
`options` and `suggestions` provide native selector projections;
their signatures have no actor argument, so the host must choose their exposure. `FormAnalytics` supplies domain bookkeeping; it is not the
complete submission entrypoint. Export options can include sensitive data, so
scope downloads and avoid exposing private local filesystem paths to clients.
Raw entry models contain personal data; let the host resource decide disclosure.

Map safe validation/domain failures without exposing SQL, tokens, signed timing,
private callback context or storage paths. Keep direct-service tests for unknown
keys, actor substitution, stale revisions, locale patch/replace, idempotent replay,
registration conflicts, custom receipts and rollback. Add host HTTP tests only
for authentication, request mapping, response privacy, origin and header behavior.
