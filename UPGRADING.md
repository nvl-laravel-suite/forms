# Upgrading NVL Forms

## Consumer contracts, committed events and runtime policy (5.x)

Prefer focused public interfaces in constructor injection; native implementations remain container defaults and host prebindings win. Returned models are documented identity/data handles: use package contracts for reads/writes and capability-specific batch readers instead of direct package queries. Enable the shipped Core PHPStan include in your host; do not invoke the suite workbench static audit command in a consumer.

Events now carry immutable schemaVersion=1 and scalar/DTO snapshots. Replace model-bearing event fields with the IDs listed in [events](docs/events.md); load only through an authorized public reader when needed. Only six declared legacy `*Event` names are retained as PHP aliases for major 5, removal no earlier than major 6. Migrate exact imports/listeners/fakes to canonical names, replace suffix wildcard patterns explicitly, drain old queued payloads, rebuild event caches and restart workers. Framework Verified/PasswordReset remain native classes. Source-connection callbacks are process-local after-commit publication, not a durable outbox or exactly-once delivery.

Package failures have a marker and optional response metadata. Opt into Core's JSON renderer deliberately; preserve existing host handlers and request-locale selection. Missing required host adapters produce `binding_required`/500; genuine configured authorization denial retains native handling. See the README error table and required-bindings section where applicable.

Factories ship in runtime package mappings for host tests. Ordinary make may persist parents; withoutParents()->make creates detached fixtures. Supply persisted native owners/parents and active tenants explicitly, retain source revisions, and never treat a factory row as a real storage/provider/workflow effect. Core's optional installer publishes common config without enabling features; strict Doctor and explicit deployment cache/worker steps belong in the host release process. C3/C4/E executable acceptance is pending until recorded by integration.


## Tenant adoption

Use the Forms adapter to assign each Form root through a reviewed map; child
ownership is derived from that Form. Do not map entries, origins, receipts,
rate limits, analytics, or translations independently.

## Upgrading to 1.0

Version 1.0 stores localized definition content only in dedicated translation rows, disables all routes, removes frontend scaffolding, and makes Activity optional.

1. Set `nvl-forms.migrations.enabled=false` for an existing schema.
2. Run `php artisan nvl:forms:doctor --strict --format=json`.
3. Backfill dedicated translation rows in an application-owned reversible bridge.
4. Replace old submission DTOs with `SubmitFormPayload`; do not send `submittedFrom`, which is now request-derived.
5. Supply expected revisions when mutating definitions.
6. Convert CORS JSON to `FormCorsSettings` camelCase keys and restrict methods to `GET`, `POST`, and `OPTIONS`.
7. Decide whether each form allows repeat registration. Forms that disable it require an email or active session and need the new registration-fingerprint uniqueness constraint.
8. Migrate custom resolvement integrations to durable submission receipts and send an idempotency key for retryable clients.
9. Replace `FormCreatedEvent` and `FormEntryCreatedEvent` listeners with `FormChangedEvent` and sanitized `FormEntryChangedEvent`.
10. Remove references to `forms.secret_key`, handler tokens, thank-you configuration, `FormHandlerTokenMiddleware`, `FormLookupService`, and `PublicFormPresentationService`.
11. Bind privacy, deletion, spam, handler, and renderer extensions as needed.
12. Enable public and management routes independently, with explicit authentication, a registered management gate, public throttling, and origin policy.

Public render/schema consumers should adopt `PublicFormRenderPayload` and `PublicFormSchemaPayload`. Render extension translations now have one canonical key: `extension_translations`.

Run the strict doctor after schema adoption. It now requires the custom submission receipt table, registration indexes, numeric `spam_score`, foreign keys, application key, security bindings, public throttle middleware, management authentication, and a registered management gate.

## Shared Doctor integration

The loaded package provider now contributes its existing inspection checks to Core's `nvl:doctor --strict --format=json`. The package command remains available. The shared gate fails errors and, in strict mode, warnings; no data upgrade is required for diagnostics.

## Next major: isolated schema identities

This is a breaking schema identity change. Back up storage and migration history, pause writes/workers, install this code with automatic package migrations disabled, and select one owner for migrations (vendor or published).

```sh
php artisan nvl:doctor --strict --format=json
php artisan nvl:schema:upgrade --package=forms --claim-legacy --migration-owner=vendor --dry-run --format=json
php artisan nvl:schema:upgrade --package=forms --claim-legacy --migration-owner=vendor --format=json
```

The command validates released columns and relational keys plus creating migration history, renames owned legacy tables to the effective `tables.*` targets and rewrites exact package migration identities while retaining batches and unrelated host records. It refuses foreign/incomplete shapes and conflicting targets. Explicit old table mappings retain those names; remove them when choosing new defaults. A second run is empty.

Declare each published path and canonical identity explicitly in `nvl-core.migrations.published`; retimestamped history also needs an exact `legacy` mapping. Use `--migration-owner=vendor` after manually archiving declared copies outside loaded paths, or `--migration-owner=published` after manually replacing executable copies with current migration code and disabling vendor loading. The plan verifies ownership and preserves batches; checksums do not automatically claim files. Modified host copies remain host-owned. No migration files or stored morph types are rewritten.

DDL transactions are driver dependent and per connection. Inspect dry-run warnings for MySQL/MariaDB or split storage; after a failure, inspect completed steps before resuming. Schema-qualified rename targets require an explicit host schema move first. Re-enable your selected migration owner, run `nvl:schema:preflight` with the same selected paths and connection, then migrate remaining package changes and rerun Doctor before resuming writes. See the suite upgrade guide for shared owner/locale inputs, Core option defaults and one-major deprecation rules.

## Major 5 cache and lock identities

Export progress entries use `nvl:forms:export-progress:<actor>:<form>:<export>` instead of `export_progress_...`. Update any host progress reader to this prefix; completed CSV paths and export identities are unchanged. Generic host entries are never copied or removed.

Drain old mutation workers and maintenance processes, then wait for their outstanding lock leases to end before starting the new major across all nodes. Running old and new lock prefixes concurrently would create independent serialization domains. Restart workers after cutover; preserve host-selected stores and keys, and do not flush a shared cache to remove old NVL entries.

## Consumer PHP surface

`FormSubmissionContext::fromRequest()` is an internal HTTP ingress adapter. Hosts that called it should use the package public submission endpoint, or pass a trusted `FormSubmissionContext` to `HandlePublicFormSubmissionAction`. Keep the normal CSRF, signed-token, origin, and actor checks; do not fabricate request security metadata to satisfy them. The context constructor, `resolvedIpAddress()`, and `httpRequest()` retain their existing signatures.

## Tagged consumer PHP boundary

Use source `@api` workflows, extension contracts, and value types for application integration. Direct use of untagged implementations or `@internal` members is unsupported. This classification keeps existing concrete Action signatures and runtime behavior; it does not authorize package model persistence, ad hoc queries, relation traversal, or generic model serialization. Returned models are identity/result handles with only the explicitly declared in-memory read fields described in the README.

The implementation Actions `RecordAllowedOriginUsageAction`, `AddFormEntrySecurityFlagAction`, `CheckFormRateLimitAction`, `DetectFormSubmissionSpamAction`, `GenerateExportFilenameAction`, `GetFormNavigationAction`, `PersistFormEntryAction`, `RecordFormRateLimitAction`, `ValidateFormEntryOwnershipAction`, `ValidateFormHostAccessAction`, `BuildFormShowPayloadAction`, `GuardCustomFormSubmissionAction`, `HandleFormSubmissionErrorAction`, `PrepareFormSubmissionDataAction`, `RecordFormAnalyticAction`, `RecordFormSubmissionAction`, `RecordFormViewAction`, `TransformFormDataForRenderAction`, `ValidateFormSubmissionProtectionAction` are explicitly internal. Use `HandlePublicFormSubmissionAction` through the public submission endpoint or with trusted `FormSubmissionContext` for the complete protection, spam, rate-limit, persistence, and after-commit workflow. Use `GetFormForRenderAction` for rendering/navigation, `CreateFormEntryAction` for the supported entry workflow, `ExportFormEntriesAction` for export, and `GetFormAnalyticsBundleAction`/`GetFormAnalyticsSummaryAction` for analytics. Preserve the public endpoint security checks when migrating helper calls.

`EntryCallbackRegistry::dispatch()` and `dispatchTenant()` are internal delivery steps. Register callbacks through the public registry methods and let the submission workflow deliver them after the outermost transaction commits. Direct dispatch would bypass the package delivery lifecycle.

`FormPayload::fromModel`, `FormEntryPayload::fromModel`, and `FormRateLimitAttemptResult::allowed`/`denied` are internal storage/protection helpers. Use the protected rendering or management endpoints for display payloads and the complete public submission workflow for rate-limit handling. The PHP `GetFormForRenderAction` and `ShowFormEntryAction` return model identity handles, not display DTOs; do not project them through these factories.

## Major 5 workflow injection

Replace host constructor dependencies on selected concrete Actions with their focused `Nvl\Forms\Contracts\*Contract` equivalents listed in the README. Existing equivalent workflow contracts are reused. Native concrete constructors, qualifiers, argument defaults, result types, and execution behavior remain compatible. Internal package Action/service chains retain their existing concrete dependencies.

Default workflow registrations use `bindIf`, retaining host interfaces/instances registered before discovery. Register substitutes at the interface key; newly resolved host services receive late replacements. Substituting a workflow does not exercise the native authorization, storage, or lifecycle invariants, which require the owning integration coverage.

`CreateFormContract` and `CreateFormEntryContract` defaults are now conditional. Existing handler, spam, rate-limit, privacy, and submission-protection behavior remains in the native workflows.
