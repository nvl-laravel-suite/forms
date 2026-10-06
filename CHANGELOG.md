# Changelog

All notable changes to `nvl/forms` are documented here.

## [Unreleased]

### Changed

- Isolate owned cache and lock keys under `nvl:forms:`; preserve generic host entries and explicit store choices.

- Prepare lockstep major 5 with required and development NVL peer floors of `^5.0`. This candidate has not been tagged or published.
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
  connection-aware date format for PostgreSQL, MySQL, MariaDB, and SQLite.
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
