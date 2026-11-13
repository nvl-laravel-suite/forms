# Changelog

All notable changes to `nvl/forms` are documented here.

## [Unreleased]

### Fixed

- Match Unicode form searches case-insensitively across SQLite (`nvl_utf8_lower`) and PostgreSQL (`COLLATE pg_c_utf8`), retain entry flag attributes on paginated projections, and route submission, receipt, rate-limit, and lifecycle side effects through configured database connections.

## [7.0.4] - 2026-11-11

### Fixed

- Keep builder-level `forceDelete()`, `updateOrInsert()` and `updateFrom()` on form entries and analytics inside the ownership boundary by adopting Core's owned write trait on both builders. Require Core 7.0.6.
- Match a submission's origin with its explicit port, so an allowed origin such as `embed.example.test:8443` admits that origin instead of refusing every submission. `submitted_from` and the analytics origin now carry a non-default port.
- Write the collection CSV formatter (`FormEntryExportService::generateCsvContent`) without an escape character, so a value containing `\"` can no longer break out of its cell and start a formula in the next one.
- Treat a malformed form or entry identifier as not found on every driver instead of raising a PostgreSQL type error; entry lists treat a malformed form or IP filter as matching nothing.
- Keep the handle of a soft-deleted form reserved: creating, renaming or duplicating onto it reports the handle conflict (or picks the next free suffix) instead of failing on the unique index.
- Give a form created without a handle a unique generated one (`contact`, `contact-1`, …; `form` when the name has no sluggable characters) instead of failing on the second form with the same name.
- Accept a numeric-string `expected_revision`, and keep the stored handle and hourly rate limit when an update or create passes them as explicit null instead of failing on a required column.
- Resolve a `CustomFormHandler` instance registered directly in `FormHandlerRegistry`; it was ignored unless it was also invokable.
- Move an entry between `submissions_count` and `spam_count` when it is marked as spam or legitimate, so a later delete no longer decrements a counter the entry never incremented.
- Make `nvl:forms:doctor` inspect the configured package connection (`nvl-forms.connection`) instead of the default one.
- Canonicalise the submission IP address, so text variants of one IPv6 address share a rate limit, reputation and stored value on every driver.
- Check a single-registration duplicate after origin and rate limiting, and keep the consumed attempt, so repeated registration probes are rate limited like any other submission.
- Search forms by the literal term: `%` and `_` in `search`, `options` and `suggestions` are no longer wildcards.
- Refuse public submission text that is not valid UTF-8 or contains NUL bytes, and an origin host longer than 253 characters, with a validation error instead of a database or JSON failure; a malformed user agent is stored with its invalid bytes replaced.
- Fail the CSRF check, and fall back to the signed public token, when the supplied session holds no token instead of raising a type error.
- Publish `connection` (default null) and the `suspicious_user_agent` spam weight in the configuration file; both were already read. Correct the README's `idempotency_key` protection key.

<!-- NVL GENERATED CHANGES START -->
### Changed

- scope builder writes and harden entries, handlers and identifiers
<!-- NVL GENERATED CHANGES END -->

## [7.0.3] - 2026-11-10

### Changed

- Remove the obsolete Spatie Laravel Data development dependency. Form test adapters now use native validation and projections with explicit omission, null and loaded-relation behavior.

<!-- NVL GENERATED CHANGES START -->
### Changed

- retire Spatie Data test dependency
<!-- NVL GENERATED CHANGES END -->

## [7.0.2] - 2026-11-09

### Changed

- Refresh the inherited Ownership deletion API reference for ordinary models and native pivots.

<!-- NVL GENERATED CHANGES START -->
### Changed

- refresh inherited ownership deletion contracts
<!-- NVL GENERATED CHANGES END -->

## [7.0.1] - 2026-11-05

### Fixed

- Enforce submission payload depth and item limits on the serialized JSON shape, including nested objects and `JsonSerializable` values, before submissions or entry updates can write data.
- Validate partial availability-date updates against retained boundaries on the current form, and reject enabling a reversed stored window without advancing its revision.

<!-- NVL GENERATED CHANGES START -->
### Changed

- enforce payload budgets and partial date windows
<!-- NVL GENERATED CHANGES END -->

## [7.0.0] - 2026-11-03

- Adopt the reviewed NVL 7 family for PHP 8.4+ and Laravel 13, with compatible required and optional NVL peers. Review the package upgrade guide before deployment.

### Changed

- Align NVL peers and consumer contracts on the coordinated 7.x line. Review [UPGRADING.md](UPGRADING.md) for adoption and application-owned integration requirements.
- Stream native entry exports from bounded private cell snapshots with current authority/source checks and explicit local-filesystem budgets.
- Add disabled-by-default, explicitly authorized per-Form retention with no inferred deletion ages; preserve entry legal holds and isolate verified export files.

## [6.0.1] - 2026-10-21

### Changed

- Share configured Form registration, queue capture and native backfill. Delegate legacy model stamping through the compatibility boundary while preserving inherited children and submission receipts.
- Require optional Ownership >=6.0.1 when installed so new shared backfill callers cannot resolve an older runtime. Require Core >=6.0.1 for the shared model, queue or compatibility implementation.

## [6.0.0] - 2026-10-20

### Changed

- Integrate configured Form roots, inherited entry records and verified public owner-site admission. Preserve origin, token, spam, idempotency and captured analytics/worker boundaries.
- Align required/development NVL peers on `^6.0`, optional peer conflicts on major 6, and `dev-main` on `6.x-dev`. Review [UPGRADING.md](UPGRADING.md) before rollout.

## [5.0.1] - 2026-10-08

### Changed

- Correct published-family verification and adoption guidance for local Dagger CI; runtime contracts are unchanged.


## [5.0.0] - 2026-10-08

### Added

- Added focused injectable contracts for all 23 selected public workflows, with native signatures and conditional defaults preserving host bindings.


### Changed

- Classify the supported consumer PHP surface with explicit source annotations and restrict package model handles to declared identity and in-memory read fields; preserve existing workflow behavior and concrete signatures.
- Isolate owned cache and lock keys under `nvl:forms:`; preserve generic host entries and explicit store choices.

- Adopt lockstep major 5 with required and development NVL peer floors of `^5.0`.
- Use a plain gated route provider so host withRouting composition and cached routes are preserved.
- Namespace middleware, limiter, route, translation and publish registrations.
- Review [UPGRADING.md](UPGRADING.md) before adopting the new names and infrastructure boundaries.

## [2.2.1] - 2026-09-26

### Documentation

- Clarify public support, contribution, and private security reporting paths.

## [2.2.0] - 2026-09-25

### Changed

- Prepare `nvl/forms` for independent Composer and Git publication; require `nvl/core` for shared Support and Data services.

## [2.0.1] - 2026-09-22

### Added

- Added canonical Form tenant ownership across public submissions, origins,
  throttling, spam, receipts, analytics, callbacks, and adoption.

### Fixed

- Delay entry callbacks until the outermost transaction commits and discard them on rollback.
- Reload and lock entry lifecycle state so stale moderation, flag, and deletion requests preserve counters, flags, and current deletion-policy decisions.
- Apply configured `FormSpamDetector` implementations throughout entry and custom submission guards without adding required interface methods.
- Give every export its own file path and reject failed storage writes before reporting completion.
- Reject missing or malformed public-token signing keys and apply the same readiness check in the doctor.

## [2.0.0] - 2026-08-29

### Changed

- Established bounded Form and FormEntry Actions and contracts as the 2.0
  consumer boundary; direct consumer Forms-model queries now fail Suite audit.

## [1.0.7] - 2026-08-22

### Changed

- Aligned the documented runtime requirement with the PHP 8.4+ package
  baseline.

## [1.0.5] - 2026-08-12

### Changed

- Released unchanged under the suite's shared version.

## [1.0.2] - 2026-08-12

- Serializes atomic submission-counter timestamps through Eloquent's
  connection-aware date format for the database drivers.
- Consolidated every pre-release form schema addition into the clean-install create migrations.
- Standardized the localized-content table as `forms_i18n`.
- Made form mutation DTO validation independent from requests, route bindings, and database reads.
- Injected application environment state and added the documented `forms-migrations` publish tag.
- Enforced management authorization before DTO validation on every management route.
- Applied public availability and locale middleware consistently and allowed UUID-or-handle public URLs.
- Replaced ad-hoc render and schema arrays with public-safe generated DTO contracts.
- Removed client-controlled submission origin and derive origin only from trusted request context.
- Implemented durable repeat-registration fingerprints and custom-handler idempotency receipts.
- Replaced hard-coded CORS headers with validated form and allowed-origin policy resolution and real preflight handling.
- Isolated post-entry callbacks and removed duplicate events that serialized full entry models.
- Removed unused form secrets, handler-token middleware, presentation/lookup services, and inert configuration.
- Stored spam score numerically and expanded the doctor to inspect indexes, foreign keys, bindings, route security, gates, and signing readiness.

## [1.0.0] - 2026-08-08

- Added headless localized form definitions and secure public submissions.
- Added revision checks, idempotency, payload bounds, trusted timing, origins, throttling, and spam contracts.
- Added entry export, redaction, anonymization, and deletion policies.
- Removed consumer defaults, frontend scaffolding, legacy JSON localization, and the hard Activity dependency.
