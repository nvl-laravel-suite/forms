# NVL Forms — API and usage

## Quickstart

```sh
composer require nvl/forms:^5.0
php artisan nvl:install forms --dry-run
php artisan nvl:install forms
```

Required NVL dependencies: `nvl/core` (`^5.0`), `nvl/filterable` (`^5.0`), `nvl/translatable` (`^5.0`). Configure authorization and explicitly opt into any routes or public submission origins. Keep sensitive entry data behind authorized projections.
Review the published common config, select one migration owner, and run schema preflight before existing-table upgrades. The installer does not enable features or run migrations. Follow the detailed installation and capability sections below before invoking a storage/provider operation.

Inject `Nvl\Forms\Contracts\ListFormsContract` in a host service. After supplying the trusted inputs described above, the first public call is:

```php
use Nvl\Forms\Contracts\ListFormsContract;

/** @var ListFormsContract $capability */
$result = $capability->execute();
```

Use the [event catalog](docs/events.md) and [Testing your app](#testing-your-app) below. The suite [getting-started guide](https://github.com/nvl-laravel-suite/laravel-suite/blob/main/docs/getting-started.md) provides a complete Comments host fixture; package archives retain their own local references.


[← NVL Laravel Suite](https://github.com/nvl-laravel-suite)

For support, [open an issue](https://github.com/nvl-laravel-suite/forms/issues). For vulnerabilities, use
[private reporting](https://github.com/nvl-laravel-suite/forms/security/advisories/new). See [Contributing](CONTRIBUTING.md).

See the [installation and publishing guide](https://github.com/nvl-laravel-suite/laravel-suite/blob/main/docs/installation.md) for Composer setup, configuration, migration ownership, and agent skills.

## Quick reference

| Item | Value |
|---|---|
| Installed through | `composer require nvl/forms:^5.0` |
| Module identifier | `nvl/forms` |
| PHP namespace | `Nvl\Forms` |
| Service provider | `Nvl\Forms\Providers\FormsServiceProvider` |
| Configuration | `config/nvl-forms.php` |

## Purpose

`nvl/forms` is a headless form-definition and submission engine for Laravel 12–13 on PHP 8.4+. It owns secure form definitions, localized nested content, public rendering contracts, submissions, stored entries, analytics, and privacy operations. It does not ship an admin UI, frontend scaffold, mail provider, application-specific form types, or a required audit system.

Forms depends on `nvl/core`, `nvl/filterable`, `nvl/tenancy`, and `nvl/translatable`. `nvl/activity` is an optional event-driven integration.

## Requirements and installation

```bash
composer require nvl/forms:^5.0
php artisan migrate
```

Laravel discovers `Nvl\Forms\Providers\FormsServiceProvider`. Clean-install migrations run by default. For an application with existing form tables, set `nvl-forms.migrations.enabled` to `false`, run the doctor, and follow [UPGRADING.md](UPGRADING.md) before enabling migrations.

Optional publish tags:

```bash
php artisan vendor:publish --tag=nvl-forms-config
php artisan vendor:publish --tag=nvl-forms-migrations
php artisan vendor:publish --tag=nvl-forms-translations
php artisan vendor:publish --tag=nvl-forms-skills
```

Choose exactly one migration owner. For automatic vendor loading, leave
`nvl-forms.migrations.enabled=true` and do not publish `nvl-forms-migrations`. For
host-owned migrations, publish `nvl-forms-migrations`, set
`nvl-forms.migrations.enabled=false` before the first migration, and maintain the
copied files as application migrations. Never run both sources; Laravel
retimestamps published migrations.

The skill is published as `.agents/skills/nvl-forms`.

## First working form

Create form definitions through `CreateFormAction` and `MutateFormPayload`; do not write form or translation tables directly:

```php
use Nvl\Forms\Actions\Form\CreateFormAction;
use Nvl\Forms\Data\Mutations\MutateFormPayload;
use Nvl\Forms\Enums\FormStatus;
use Nvl\Forms\Enums\FormType;
use Nvl\Forms\Enums\Resolvement;

$form = app(CreateFormAction::class)->execute(new MutateFormPayload(
    handle: 'contact',
    translations: [
        'en' => [
            'name' => 'Contact us',
            'submitButtonLabel' => 'Send',
            'content' => [
                'sections' => [
                    ['fields' => [['name' => 'email', 'type' => 'email']]],
                ],
            ],
        ],
    ],
    status: FormStatus::ACTIVE,
    resolvement: Resolvement::ENTRIES,
    type: FormType::IFRAME,
));
```

The DTO validates locale maps and nested content. Form identity, status, availability, security settings, origins, and options remain locale-neutral. Localized names, descriptions, labels, success copy, sections, fields, options, validation messages, and provider extension copy live in `forms_i18n`.

## Mutate safely

Use these Actions as the public write boundary:

- `CreateFormAction`, `UpdateFormAction`, `DuplicateFormAction`, `DeleteFormAction`
- `CreateFormEntryAction`, `MarkFormEntryAsSpamAction`, `MarkFormEntryAsLegitimateAction`
- `RedactFormEntryAction`, `AnonymizeFormEntryAction`, `DeleteFormEntryAction`

Updates require `MutateFormPayload::expectedRevision`. A stale revision is rejected instead of overwriting a concurrent edit. Translation mode defaults to patch; use replace only when omitted locales should be deleted.

After a transaction commits, Forms dispatches the sanitized `FormChangedEvent` and `FormEntryChangedEvent`. The entry event carries the entry identifier rather than serializing submission PII. Subscribe to those events for notifications, activity capture, indexing, or other application behavior.

## Extend definitions and rendering

- Register custom submission behavior with `FormHandlerRegistry` and `CustomFormHandler`.
- Register supplemental render data with `FormRenderDataRegistry` and `FormRenderDataProvider`.
- Register public error mappings with `FormErrorMapperRegistry` and `FormErrorMapper`.
- Register entry callbacks with `EntryCallbackRegistry`.

Registries reject duplicate keys and invalid capabilities. Providers and handlers are container-resolved; the package never imports an application model or module.

Entry callbacks run after the outermost database transaction commits, including any transaction opened by the consuming application. A rollback discards pending callbacks. Each callback is isolated and reported independently: a failed integration does not make a persisted submission appear to have failed and does not stop later callbacks.

Forms registers `forms.forms` with `TranslationResourceRegistry`. Central gathering and synchronized translation edits therefore use the same field whitelist, authorization, and optimistic concurrency rules as other localized packages.

## Public submission security

Public submission flows through `HandlePublicFormSubmissionAction` and `SubmitFormPayload`. The action composes availability, origin, token, rate-limit, honeypot, spam, payload, idempotency, persistence, callback, and response behavior.

Before enabling public routes, configure:

- explicit allowed origins and CORS behavior per form;
- CSRF or short-lived signed public-token strategy;
- `submission.max_payload_bytes`, `max_depth`, and `max_items`;
- rate limits and block windows;
- honeypot names, spam weights, and thresholds;
- a stable idempotency key for retryable clients;
- retention and privacy policy appropriate to the collected data.

Minimum-submission timing uses trusted token issue time. A client-supplied timestamp is not trusted. Reusing an idempotency key with the same payload returns the original result; reusing it with a different payload is rejected.

Submission origin is request-derived. `submittedFrom` is not part of `SubmitFormPayload`, so a client cannot replace the trusted `Origin`, `Referer`, or request-host context with a payload value.

When `allowMultipleRegistrations` is false, Forms stores a SHA-256 registration fingerprint derived from the normalized email address or, when email is absent, the active session identifier. A submission without either identity is rejected. Database uniqueness prevents concurrent duplicates without storing the source identity in the fingerprint column.

Entry submissions persist idempotency state on the entry. Custom handlers use a separate durable receipt: completed retries replay the result, changed payloads conflict, and processing or failed attempts are not automatically re-executed because downstream side effects may already have occurred. Custom handlers should still make their own external operations idempotent.

Bind custom implementations of:

- `FormRateLimiter`
- `FormSpamDetector`
- `FormEntryPrivacyPolicy`
- `FormEntryDeletionPolicy`

The supplied privacy and deletion policies are permissive building blocks, not substitutes for application policy.

Both entry and custom submissions resolve honeypot checks, scores, and blocking/flagging decisions through the configured `FormSpamDetector`. Implementations need only the existing interface; built-in diagnostic flags are included when the default detector is used. Token issuance and validation require a nonempty application key. Malformed or empty `base64:` keys fail closed, and the doctor checks the same signing readiness rule.

## CORS and iframe embedding

`FormType::IFRAME` is a presentation mode, not an authentication signal. Forms does not trust `Sec-Fetch-Dest` or custom iframe headers as proof of embedding. Restricted forms authorize the normalized request origin, and iframe responses expose a CSP `frame-ancestors` value for application middleware to apply.

Form and allowed-origin `corsSettings` use the typed `FormCorsSettings` contract:

```php
'corsSettings' => [
    'policy' => 'custom',
    'allowCredentials' => true,
    'allowWildcards' => false,
    'maxAge' => 600,
    'allowedMethods' => ['GET', 'POST', 'OPTIONS'],
    'allowedHeaders' => [
        'Content-Type',
        'X-CSRF-TOKEN',
        'X-Forms-Public-Token',
        'Idempotency-Key',
    ],
],
```

Unknown keys, unsupported methods, unsafe header names, and out-of-range preflight cache values are rejected. Real `OPTIONS` requests pass through the same availability and origin policy as render, schema, and submit requests. Allowed-origin settings override form defaults for the matching origin.

## Routes

Both route surfaces are disabled by default:

```php
'routes' => [
    'prefix' => 'nvl/api/v1',
    'middleware' => ['api'],
    'management' => [
        'enabled' => false,
        'middleware' => ['auth'],
    ],
    'public' => [
        'enabled' => false,
        'middleware' => ['throttle:nvl.forms.public'],
    ],
],
'authorization' => [
    'gate' => null,
],
```

Management routes use names beginning with `nvl.forms.management.`. Public render, schema, preflight, and submit routes use `nvl.forms.public.` and accept either a UUID or form handle. The `lang` query parameter selects a supported content locale. Availability, locale, origin, CORS, and throttling middleware apply consistently across the public surface.

Management authorization runs in route middleware before request DTO validation and is repeated at the controller boundary. The policy fails closed until `nvl-forms.authorization.gate` names a registered gate. The package does not assume an application middleware alias, frontend path, view directory, or user model.

## Public render and schema contracts

Render responses expose `PublicFormRenderPayload`: localized content and copy, status, type, locale, public restrictions, and display options. Administrative counters, usage timestamps, security secrets, and storage details are not part of the render contract. Provider translations appear only under `extension_translations`.

Schema responses expose `PublicFormSchemaPayload`. Every rule is converted to a stable string representation; PHP validation-rule objects are never serialized. The built-in schema describes the generic submission envelope and payload bounds. Application-specific fields inside `submissionData` remain the custom handler or consuming renderer's semantic validation responsibility.

## Entry privacy and operations

`ExportFormEntriesAction` exports only the selected authorized entry set. `RedactFormEntryAction` removes configured sensitive fields, `AnonymizeFormEntryAction` removes identifying values while preserving permitted aggregate data, and `DeleteFormEntryAction` delegates the final decision to `FormEntryDeletionPolicy`.

Exports use a unique file path for each invocation and fail if storage rejects the write. Moderation, security flags, and deletion reload and lock stored entry state before applying changes. Deletion policies inspect that current state; a stale model cannot bypass a legal hold or repeat a counter decrement.

Queue large exports and retention jobs in the consuming application. Do not place complete submission payloads in logs or events sent to untrusted listeners.

## Database and identifiers

Package-owned rows use UUID primary keys. The schema separates forms, form translations, entries, custom-handler submission receipts, analytics, allowed origins, and rate-limit state. Lookup, status, availability, form/locale, idempotency, registration-fingerprint, and security query paths are indexed. Spam score is stored as a numeric zero-to-one-hundred value.

Set `nvl-forms.migrations.enabled=false` only for controlled adoption. A pre-existing table is not evidence that its columns, key types, indexes, or constraints match v1.

## Commands

## Tenant ownership

A Form is the canonical tenant root for definitions, translations, entries,
submission receipts, origins, throttles, and analytics. Public site/token
resolution establishes the tenant before lookup; callback jobs carry the
captured tenant envelope and never infer ownership from request payloads.

```bash
php artisan nvl:forms:doctor
php artisan nvl:forms:doctor --strict --format=json
```

The doctor does not mutate state. It checks required tables, columns, numeric score storage, indexes, foreign keys, privacy/rate/spam bindings, application-key readiness, public throttling, management authentication, and the configured gate's registration.

## Generated TypeScript

Form DTO and enum sources register automatically with Core's Data provider under `Nvl.Forms.*`:

```bash
php artisan nvl:data:types:generate
php artisan nvl:data:types:check
```

Use generated display DTOs for clients. Storage columns and internal security state are not a stable frontend contract.

## Failure behavior

Validation failures return safe mapped errors at HTTP boundaries. Origin, token, rate-limit, spam, availability, repeat-registration, stale-revision, and idempotency conflicts are distinct failures. Database mutations are transactional, and events that imply durable state are dispatched after commit. External listeners must be idempotent.

## Verification

The runnable examples above are mirrored by package tests. Before release, run the package Pest suite, Pint, PHPStan at maximum strictness, dependency analysis, generated-type checks, and package distribution validation.

See [UPGRADING.md](UPGRADING.md), [SECURITY.md](SECURITY.md), [CONTRIBUTING.md](CONTRIBUTING.md), and [CHANGELOG.md](CHANGELOG.md).

## Injectable workflow contracts

Constructor-inject focused interfaces from `Nvl\Forms\Contracts` when composing host workflows. Each interface retains the native Action’s complete `execute` parameters, defaults, return type, and documented generic/shape result. Concrete Actions remain directly usable in major 5.

```php
use Illuminate\Contracts\Auth\Authenticatable;
use Nvl\Forms\Contracts\CreateFormContract;
use Nvl\Forms\Data\Mutations\MutateFormPayload;
use Nvl\Forms\Models\Form;

final readonly class CreateFormWorkflow
{
    public function __construct(private CreateFormContract $workflow) {}

    public function execute(MutateFormPayload $data, ?Authenticatable $actor = null): Form
    {
        return $this->workflow->execute($data, $actor);
    }
}
```

The provider installs conditional transient defaults (`bindIf`) for the following selected workflows. A host interface binding registered before package discovery is retained; a later binding/instance replacement is used by newly resolved host services. Keep authorization, validation, query ownership, and mutation behavior inside the owning package workflow.

| Contract | Native implementation |
| --- | --- |
| `AnonymizeFormEntryContract` | `AnonymizeFormEntryAction` |
| `CreateFormEntryContract` | `CreateFormEntryAction` |
| `DeleteFormEntryContract` | `DeleteFormEntryAction` |
| `ExportFormEntriesContract` | `ExportFormEntriesAction` |
| `ListFormEntriesContract` | `ListFormEntriesAction` |
| `MarkFormEntryAsLegitimateContract` | `MarkFormEntryAsLegitimateAction` |
| `MarkFormEntryAsSpamContract` | `MarkFormEntryAsSpamAction` |
| `RedactFormEntryContract` | `RedactFormEntryAction` |
| `ShowFormEntryContract` | `ShowFormEntryAction` |
| `CreateFormContract` | `CreateFormAction` |
| `DeleteFormContract` | `DeleteFormAction` |
| `DuplicateFormContract` | `DuplicateFormAction` |
| `GetFormAnalyticsBundleContract` | `GetFormAnalyticsBundleAction` |
| `GetFormAnalyticsSummaryContract` | `GetFormAnalyticsSummaryAction` |
| `GetFormForRenderContract` | `GetFormForRenderAction` |
| `GetFormSelectOptionsContract` | `GetFormSelectOptionsAction` |
| `GetFormSuggestionsContract` | `GetFormSuggestionsAction` |
| `GetFormValidationSchemaContract` | `GetFormValidationSchemaAction` |
| `HandlePublicFormSubmissionContract` | `HandlePublicFormSubmissionAction` |
| `ListFormsContract` | `ListFormsAction` |
| `SearchFormsContract` | `SearchFormsAction` |
| `ShowFormContract` | `ShowFormAction` |
| `UpdateFormContract` | `UpdateFormAction` |

## Supported PHP usage

The source `@api` declarations identify supported workflows, extension contracts, and value types. Public members marked `@internal` and untagged implementation types remain package-owned. Concrete Actions retain their existing constructors, qualifiers, and `execute()` signatures.

A package model returned or accepted by a public workflow is an identity/result handle. Use its declared type and `getKey()`, `getKeyName()`, `getMorphClass()`, `getRouteKey()`, `getRouteKeyName()`, `is()`, `isNot()`, and `relationLoaded()`. Read only explicitly declared in-memory `@nvl-consumer-read` fields; ordinary model PHPDocs and fillable attributes do not grant consumer reads. Obtain display projections through public reads. Persistence, additional model queries, relation access/loading, and generic model serialization are outside this contract. Host-model queries remain available, while traversal or aggregates of package capability relations require the package public reader or authorized adapter.

## Shared consumer diagnostics

Run `php artisan nvl:doctor --strict --format=json` to combine the read-only checks from loaded NVL package providers. Errors fail the gate, and strict mode also fails warnings. This package's existing Doctor command remains available and uses the same package-owned inspection service.

## Next major: isolated schema identities

Use `nvl-forms.tables.<logical-key>` for every table and `nvl-forms.connection` for its database connection. Null connection inherits `nvl-core.connection`, then Laravel's default. Tables are resolved at runtime by the package table definition helper.

| Logical key | New default | Previous name |
| --- | --- | --- |
| `forms` | `nvl_forms_forms` | `forms` |
| `i18n` | `nvl_forms_i18n` | `forms_i18n` |
| `entries` | `nvl_forms_entries` | `form_entries` |
| `submission_receipts` | `nvl_forms_submission_receipts` | `form_submission_receipts` |
| `allowed_origins` | `nvl_forms_allowed_origins` | `form_allowed_origins` |
| `analytics` | `nvl_forms_analytics` | `form_analytics` |
| `rate_limits` | `nvl_forms_rate_limits` | `form_rate_limits` |

Migration filenames contain `nvl_forms_`. Existing installations must complete the upgrade in `UPGRADING.md` before running new migrations. A pending creator rejects an existing target before that owned migration runs; use `nvl:schema:preflight` for an explicit whole-batch check; legacy storage with old history needs an ownership decision.

Owned cache and lock identities follow `nvl:<package>:<purpose>:…`. Export progress entries use `nvl:forms:export-progress:<actor>:<form>:<export>` instead of `export_progress_...`. Update any host progress reader to this prefix; completed CSV paths and export identities are unchanged. Generic host entries are never copied or removed. See [UPGRADING](UPGRADING.md) for coordinated worker and lock lease cutover.

## Canonical configuration ownership

Use `nvl-forms` settings in `config/nvl-forms.php` and canonical package environment names. Old generic roots are foreign unless an upgrading NVL host explicitly selects them in Core's default-off compatibility. Canonical false/null/empty values win; no old roots are populated or written back. Keep logical package/resource IDs unchanged. Review [Core's rename inventory and cache/worker cutover](https://github.com/nvl-laravel-suite/core/blob/main/UPGRADING.md#major-5-canonical-configuration-and-environment).

## Testing your app

Inject the supported contract rather than constructing its concrete Action or querying package tables. Replace `Nvl\Forms\Contracts\ListFormsContract` in Laravel's native container for a host-workflow test:

```php
use Nvl\Forms\Contracts\ListFormsContract;

$double = Mockery::mock(ListFormsContract::class);
$this->app->instance(ListFormsContract::class, $double);
// Configure the exact execute arguments and documented return value for your host case.
```

The package's conditional native binding preserves host substitutions. Production uses the real contract; test doubles do not prove its storage/authorization behavior.

A detached fixture for a returned identity/data handle is:

```php
use Nvl\Forms\Models\Form;
$fixture = Form::factory()->withoutParents()->make();
```

Ordinary `make()` may persist declared package parents. `withoutParents()->make()` disables parent expansion/admission for detached fixtures; use explicit persisted parents/owners and matching effective connections for a real `create()`. Factories do not authorize workflows, call Stripe, create backing Media objects or publish Template artifacts. Enabled tenancy requires explicit admitted persisted tenants/parents. Your host test installation supplies Faker; no test runner is a runtime package dependency.

Use Laravel `Event::fake()`, `Queue::fake()`, `Mail::fake()` or `Storage::fake()` only for the effects the host test intends to isolate. Use real commits/listeners for timing proof. Add the optional Core consumer boundary rules to host PHPStan:

```neon
includes:
    - vendor/nvl/core/support/consumer-audit.neon
parameters:
    nvlConsumer:
        testPaths: [tests]
        tableNames: []
        exceptions: []
```

Rules read installed public metadata without suite boot. They flag internal symbols, package model queries/writes, capability relations and owned tables; they cannot prove dynamic code or runtime authorization. Exact exceptions require `file`, `identifier`, `symbol`, and a documented `reason`. The published 5.x family is verified through the local Dagger release gate on PHP 8.4/Laravel 13, including owning suites, MySQL/PostgreSQL persistence contracts and sealed Tenancy consumers. Fresh public Composer installation, discovery and configuration/route caching are verified. PHP 8.5, Laravel 12, MariaDB and the full independent archive matrix require separate evidence. See the [verification and release policy](https://github.com/nvl-laravel-suite/laravel-suite#verification-and-releases).

### Shipped factory states

These runtime builders keep Laravel's native Factory API. The listed methods name explicit supported parent/owner/lifecycle states; follow each factory's native admission requirements. Detached examples above do not assert persistence validity.

| Factory | Explicit states |
| --- | --- |
| [`AllowedOriginFactory`](database/factories/AllowedOriginFactory.php) | `forForm(Form $form)` |
| [`FormAnalyticFactory`](database/factories/FormAnalyticFactory.php) | `forForm(Form $parent)` |
| [`FormEntryFactory`](database/factories/FormEntryFactory.php) | `forForm(Form $form)` |
| [`FormFactory`](database/factories/FormFactory.php) | `withoutTranslations()` |
| [`FormRateLimitFactory`](database/factories/FormRateLimitFactory.php) | `forForm(Form $parent)` |
| [`FormSubmissionReceiptFactory`](database/factories/FormSubmissionReceiptFactory.php) | `forForm(Form $form)` |
| [`FormTranslationFactory`](database/factories/FormTranslationFactory.php) | `forForm(Form $parent)` |

## Error codes and events

All recognized package failures implement `Nvl\Support\Contracts\PackageException`; only `RespondableException` opts into safe response metadata. Keep native PHP programmer errors and Laravel/SDK exceptions distinct. The optional `PackageExceptionRenderer` is registered by the host in `withExceptions`; it leaves unrelated, marker-only and non-JSON handling to the host. Its JSON envelope is `{message:string, code:string, context:object}`. Request locale is host-owned; diagnostics/previous exceptions are not public copy. Event schemas and source connections are documented in [events](docs/events.md).

The table lists enum discriminators, including any successful codes retained for compatibility. A code is not itself an HTTP status; the throwing exception's `suggestedStatus()` is authoritative, especially legacy/custom constructors. Empty context renders as `{}`; only documented JSON-safe context is presented.

| Code | Suggested status | Public context | Translation key |
| --- | --- | --- | --- |
| `submission_rejected` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-forms::responsecode.submission_rejected` |
| `ownership_denied` | 403 | Declared safe scalar/array map; otherwise `{}` | `nvl-forms::responsecode.ownership_denied` |
| `created` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-forms::responsecode.created` |
| `updated` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-forms::responsecode.updated` |
| `deleted` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-forms::responsecode.deleted` |
| `duplicated` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-forms::responsecode.duplicated` |
| `create_failed` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-forms::responsecode.create_failed` |
| `update_failed` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-forms::responsecode.update_failed` |
| `delete_failed` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-forms::responsecode.delete_failed` |
| `operation_failed` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-forms::responsecode.operation_failed` |


## License

Released under the [MIT License](LICENSE).
