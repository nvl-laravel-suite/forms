---
name: nvl-forms
description: Implement, integrate, test, or review nvl/forms in Laravel 13. Use for headless localized form definitions, secure public submissions, entries, idempotency, origins, throttling, spam contracts, renderer or handler registries, privacy operations, management authorization, or optional activity integration.
---

# NVL Forms

Keep definitions, public rendering, submissions, and entry operations behind package Actions. Forms is headless and must install without `nvl/activity`.

In tenant deployments, resolve the public site/token before Form lookup and
derive every entry-side record from the canonical Form. Never accept tenant
identity in submission DTOs or restore it from ambient callback state.

## Manage definitions

- Validate `MutateFormPayload`.
- Use `CreateFormAction`, `UpdateFormAction`, `DuplicateFormAction`, and `DeleteFormAction`.
- Require the expected revision for edits.
- Store all localized form, section, field, option, and message copy through `nvl/translatable`.
- Register custom handlers, render data, and error mappers through their registries; duplicate keys must fail.

## Submit safely

- Use `HandlePublicFormSubmissionAction` with `SubmitFormPayload`.
- Keep public routes disabled unless explicitly enabled.
- Treat submission origin as request-derived; never accept `submittedFrom` from public payloads.
- Configure allowed origins and typed `FormCorsSettings`; enforce iframe embedding with origin policy and CSP rather than spoofable request headers.
- Configure CSRF or signed tokens, rate limits, payload bounds, idempotency keys, repeat-registration identity, and spam detection deliberately.
- Supply a nonempty application key; malformed `base64:` keys cannot issue or validate public tokens.
- Bind `FormSpamDetector` for custom honeypot, score, and threshold decisions in both entry and custom submissions; its existing methods are sufficient.
- Treat trusted token issue time as the minimum-submission-time source.
- When repeat registrations are disabled, require normalized email or an active session and preserve the fingerprint uniqueness constraint.
- Preserve custom-handler receipts. Completed retries replay; changed, processing, or failed receipts conflict instead of re-running unknown side effects.
- Return safe validation and rejection responses without leaking handler or storage details.

## Protect stored entries

- Use `ExportFormEntriesAction`, `RedactFormEntryAction`, `AnonymizeFormEntryAction`, and `DeleteFormEntryAction`.
- Bind `FormEntryPrivacyPolicy` and `FormEntryDeletionPolicy` for application decisions.
- Evaluate moderation, security flags, counters, and deletion policies against freshly locked entry state.
- Preserve unique export paths and treat rejected storage writes as failures.
- Deliver notifications and optional audit activity from `FormChangedEvent` and sanitized `FormEntryChangedEvent`.
- Keep entry callbacks best-effort and isolated after the outermost commit; rollback discards pending callbacks.
- Run `nvl:forms:doctor --strict --format=json` before enabling routes or adopting tables.

## Verify

Test installation without Activity, revisions, nested translations, public DTO serialization, UUID/handle routes, locale middleware, origins, real preflight behavior, throttling, tokens, spam, payload limits, registration and idempotency races, callback isolation, privacy policies, sanitized after-commit events, pre-validation route authorization, and query counts.

## Configurable-tenancy release discipline

- Preserve disabled compatibility and package independence; tenant support never creates an undeclared Auth or Suite dependency.
- Use registered package-owned resources, adoption adapters, Actions, and lifecycle APIs. Never add a generic tenant delete-all path or raw cross-package cleanup.
- Treat mapping/configuration hashes, interruption checkpoints, conservation evidence, worker context, tenant-leading queries, and standalone consumption as release contracts.

## Shared consumer diagnostics

Run `php artisan nvl:doctor --strict --format=json` to combine checks from loaded NVL providers. Retain the package Doctor command for its detailed report; both paths reuse the package-owned inspection service.

### Brownfield storage identities

Resolve all package tables through the table helper and canonical `nvl-forms.tables.*`, connections through `nvl-forms.connection` with Core/Laravel inheritance. Defaults use `nvl_forms_*`; migration filenames include that package slug. Never silently adopt a matching table or generic migration filename. Run shared `nvl:doctor --strict --format=json` and the explicit `nvl:schema:upgrade --package=forms --claim-legacy --dry-run --format=json` before upgrading owned legacy storage. Validate the complete plan and choose one migration owner. Preserve host records, constraint names and stored morph values. Deprecated config inputs last one major; canonical options take precedence.

## Cache and lock ownership

Export progress entries use `nvl:forms:export-progress:<actor>:<form>:<export>` instead of `export_progress_...`. Update any host progress reader to this prefix; completed CSV paths and export identities are unchanged. Generic host entries are never copied or removed.

Drain old mutation workers and maintenance processes, then wait for their outstanding lock leases to end before starting the new major across all nodes. Running old and new lock prefixes concurrently would create independent serialization domains. Restart workers after cutover; preserve host-selected stores and keys, and do not flush a shared cache to remove old NVL entries.

## Canonical configuration ownership

- Read/write `nvl-forms` configuration and publish only canonical `nvl-<package>-<resource>` tags. Keep logical package/tenant resource identifiers unchanged.
- Generic config roots and unprefixed package environment names are foreign by default. For an upgrading NVL host only, select `nvl-core.compatibility.legacy_config` package IDs and `legacy_env` explicitly; both default off. Canonical presence wins, including false/null/empty values. Legacy inputs are read without writing back and are removed in major 6.
- Use canonical `NVL_<PACKAGE>_*` variables only in config evaluation, then rebuild configuration caches and restart workers after cutover. Shared Laravel environment variables retain their names. Consult Core's versioned `support/resources/global-names.json` for all renames.
- Old global aliases and legacy route families require separate explicit `global_aliases`/`legacy_routes` package selections. Preserve collisions and use Doctor diagnostics; never grant generic permissions automatically or claim signed-link compatibility without the same authorization/signature checks.

## Compose host workflows through contracts

- Inject focused `Nvl\Forms\Contracts\*Contract` interfaces for the 23 selected public Actions listed in the README; reuse existing equivalent contracts. Preserve the exact native `execute` parameters/defaults and DTO/model/vendor results.
- Use host interface bindings for substitution. Conditional defaults retain prebindings; late replacement reaches newly resolved host services. Keep private package constructor chains concrete and preserve package-owned authorization, queries, validation and mutation semantics.


## Consumer runtime and testing contracts

Start with the package README Quickstart and Testing your app sections. Use `nvl:install <package>` for loaded-package common config publication; it does not enable features, run schema or refresh caches. Preserve native host owner keys/morph maps and selected auth/tenancy defaults. Read full runtime defaults and publish advanced config only deliberately.

Inject the supported focused interfaces and preserve host bindings. Returned model handles do not permit package-table queries/writes outside documented capability/extension seams. Host tests may substitute contracts in Laravel's container, use shipped model factories (ordinary make may persist parents; withoutParents()->make is detached), and use Laravel effect fakes deliberately. Only Media/Stripe have dedicated provider/library fakes; do not invent a universal package fake. Settings InteractsWithSettings is definition-only. Host PHPStan may include vendor/nvl/core/support/consumer-audit.neon; no unpublished workbench command is a consumer requirement.

Read docs/events.md and the package README error table. Domain events use schemaVersion=1, model-free facts and actual source-connection commit callbacks; only six declared old Event suffix aliases remain for major 5. Migrate exact listeners/fakes and suffix wildcards, drain old queued payloads, rebuild event cache and restart workers. Delivery is not a durable outbox. The Core exception renderer is opt-in, JSON-only for respondable failures, with exactly message/code/context and host-selected locale. Do not expose diagnostics or reinterpret missing bindings as authorization denial.

Core package logging uses nvl/normal with CSV quiet by default, stable message keys and bounded context; incidents survive quiet. Do not mutate global logger context or log raw row/provider/content/credential payloads. Run only authorized project checks and report new acceptance as pending until actual output exists.
