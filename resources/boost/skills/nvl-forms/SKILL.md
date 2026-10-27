---
name: nvl-forms
description: Integrate native localized form lifecycle, secure submissions, durable custom receipts, entry privacy and optional ownership in Laravel 13.
---

Use concrete `FormLifecycle`, `FormReader`, `FormSubmissions`, `FormEntries` and `FormAnalytics` services with native models and arrays. Read the installed README for exact entry points and protection keys. Do not recreate Actions, DTO wrappers, package HTTP routes, TypeScript registration or deprecated Tenancy branches.

Supply explicit authorized management actors. Updates require expectedRevision; preserve translation patch/replace and arbitrary nested locale content. Public submission must use the complete `FormSubmissions::submit` operation. Consumers extract origin, IP, session, tokens and idempotency metadata. Keep signed timing, origin/token expiry, CSRF, throttling, honeypot, registration uniqueness and failure bookkeeping intact.

Inspect the returned model: FormEntry for ordinary submission, durable FormSubmissionReceipt for custom handlers; receipt.result_id identifies external work. Do not synthesize an entry for external results. Extensions receive array context. Callbacks run after source commit and do not promise crash durability.

Native owned hosts admit a verified canonical origin through PublicOwnerSiteRunner before lookup. Derive child authority from the canonical Form, never request owner fields. Preserve parent constraints, owner/site/generation token binding and fresh named extensions.

Select the Form subclass at nvl-forms.models.form before providers boot. Bind consumer FormRateLimiter/FormSpamDetector adapters before package registration; native scopedIf defaults preserve those extension bindings and request/job lifetimes.

Run affected Pest tests, package maximum analysis and scoped Pint. Consumer HTTP and DTO examples remain active under tests/Fixtures/Consumer.
