---
name: nvl-forms
description: Use when integrating nvl/forms form lifecycle, localized form rendering, protected public submissions, custom receipts, entry privacy, analytics, or optional native Ownership.
---

# NVL Forms

Use the native PHP 8.4 / Laravel 13 services. Consumers own their requests,
controllers, routes, Actions, DTOs, resources, UI and response mapping.

## Read only the needed reference

- [Installation and configuration](references/installation.md): dependencies, migrations and supported publication.
- [Native API](references/api.md): exact public declarations, contracts and return types.
- [Usage recipes](references/usage.md): authorization, lifecycle, submissions, extensions and privacy.

## Choose the complete operation

| Need | Service |
| --- | --- |
| Create, update, duplicate, delete a form | `FormLifecycle` |
| Resolve form/schema, management reads, search, analytics summaries | `FormReader` |
| Accept a public submission, including custom handlers | `FormSubmissions::submit` |
| Entry reads, edits, moderation, export and privacy | `FormEntries` |
| Explicit bounded database and private-export retention | `FormRetention` |
| Views, attempts, submission counters and origin usage | `FormAnalytics` |
| Issue or validate signed public timing tokens | `PublicFormTokenService` |
| Register custom work and committed callbacks | `FormHandlerRegistry`, `EntryCallbackRegistry` |

`FormSubmissions::submit` owns the security workflow. Individual bookkeeping,
spam, token or persistence helpers do not replace it.

## Authority and identity

Configure `nvl-forms.authorization.gate` or management remains denied.
Management takes an explicit actor and reloads canonical Eloquent identity and
facts before the Gate. Non-Eloquent providers retain their own freshness contract.
Null is anonymous; it never grants management or trusted background authority.

In an owned host, verify the canonical public origin with `PublicOwnerSiteRunner`
before form lookup, token issuance or submission. A public owner/site admission
cannot manage forms. Child authority derives from the canonical parent Form.
Never accept actor, owner scope, site admission or trusted timing from input.

## Native input and results

- Lifecycle arrays use camelCase attributes; update requires `expected_revision`.
- Translation patch retains omitted locales; explicit replace removes omitted locales.
- Localized form content may contain arbitrary nested consumer fields.
- Unknown/protected submission root keys fail; custom data belongs in `submissionData`.
- Preserve omitted fields and explicit null according to the operation's schema.
- Pass origin, IP, session, CSRF, public token and retry metadata separately.
- Ordinary submission returns `FormEntry`; custom submission returns `FormSubmissionReceipt`.
- A receipt's `result_id` identifies external work; never manufacture a FormEntry for it.

Keep origin restrictions, token expiry, signed timing, CSRF, honeypot, throttling,
registration uniqueness, idempotent payload checks and failure bookkeeping intact.
The host chooses the transport response for safe validation/domain failures.

## Extensions and optional capabilities

Select `nvl-forms.models.form` before providers boot. Extend `Form` while retaining
native UUIDs, casts, ownership and persistence hooks; use host migrations for added
columns. Fixed entry, translation, origin and receipt records have no model selector.

Bind `FormRateLimiter` and `FormSpamDetector` before package registration when
replacing the scoped defaults. Register named fresh handlers/callback classes for
owned public flows; do not retain request or owner-scoped service objects.
Core and Translatable are runtime foundations; Ownership and audit consumers are optional.

## Transactions and verification

Lifecycle and entry operations own their source-connection transactions. Events
follow the actual outer commit. Entry callbacks run after commit and are not a
crash-durable delivery queue. Custom receipts retain durable claim/replay state;
the downstream handler must make its own external work retry-safe.

`FormEntries::export` snapshots sanitized cells within `nvl-forms.exports` row,
dynamic-column and byte limits, then streams to a private local disk; it returns
an absolute local path and rejects remote disks or overflow. Names, messages and
dynamic fields may contain personal data even without the sensitive-data columns.
`FormRetention::prune` is disabled by default: configure positive ages, explicit
host authority and budgets, and run outside all host transactions, including dry
runs. Existing privacy and legal-hold rules remain authoritative. Completed rows
commit individually; a later failure preserves those deletions and their real
facts. Repeat when `has_more` is true; legacy flat exports need reviewed cleanup.
Public `get`/`schema` reads do not record views. Management `details` can use
`recordView: false`; hosts explicitly recording public views must throttle them.

Test direct service authority, revision conflicts, translation modes, public
protection, retries, custom receipts, privacy and rollback. For owned hosts include
canonical origin/site binding and fresh callbacks. Use [usage](references/usage.md)
for the exact integration choices; report only verification actually performed.
