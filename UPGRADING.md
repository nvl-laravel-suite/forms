# Upgrading NVL Forms

## Tenant adoption

Use the Forms adapter to assign each Form root through a reviewed map; child
ownership is derived from that Form. Do not map entries, origins, receipts,
rate limits, analytics, or translations independently.

## Upgrading to 1.0

Version 1.0 stores localized definition content only in dedicated translation rows, disables all routes, removes frontend scaffolding, and makes Activity optional.

1. Set `forms.migrations.enabled=false` for an existing schema.
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
php artisan nvl:schema:upgrade --package=forms --claim-legacy --dry-run --format=json
php artisan nvl:schema:upgrade --package=forms --claim-legacy --format=json
```

The command validates released columns and relational keys plus creating migration history, renames owned legacy tables to the effective `tables.*` targets and rewrites exact package migration identities while retaining batches and unrelated host records. It refuses foreign/incomplete shapes and conflicting targets. Explicit old table mappings retain those names; remove them when choosing new defaults. A second run is empty.

Unmodified published files, including changed timestamps, map by verified checksum to the exact vendor migration identity and current package migration implementation. Modified host copies remain host-owned. Disable vendor loading when retaining a published owner; duplicate ownership fails before migration. No migration files or stored morph types are rewritten.

DDL transactions are driver dependent and per connection. Inspect dry-run warnings for MySQL/MariaDB or split storage; after a failure, inspect completed steps before resuming. Schema-qualified rename targets require an explicit host schema move first. Re-enable your selected migration owner, migrate remaining package changes and rerun Doctor before resuming writes. See the suite upgrade guide for shared owner/locale inputs, Core option defaults and one-major deprecation rules.
