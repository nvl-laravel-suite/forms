# Upgrading Forms to NVL Light

Replace one-operation Actions and their Contracts with `FormLifecycle`, `FormReader`, `FormSubmissions`, `FormEntries` and `FormAnalytics`. Pass native arrays and an explicit management actor. Package DTOs, HTTP controllers/routes/middleware, TypeScript registration, legacy event aliases and deprecated Tenancy integration are removed without compatibility aliases.

Public consumers call `FormSubmissions::submit($form, $fields, $protection)`. Its complete security and persistence workflow returns `FormEntry|FormSubmissionReceipt`. For a receipt, project `result_id`; do not expect a form entry for an external handler. Reuse the consumer fixtures for request/session extraction and response projection. Existing handler and callback ports now take explicit array context, never Request.

Keep revision checks and locale maps when adapting mutation DTOs. Use `translationMode` patch/replace and pass `expectedRevision` on update. Retain the supplied native Session when using CSRF, and the verified public site context when using native Ownership.

Default models load without Ownership installed. Native owned subclasses and canonical child inheritance remain supported. The deprecated bridge and automatic old-schema conversion are retired; released migration files and consumer records are retained. Existing storage adoption remains an explicit native Ownership operation with reviewed mappings.

Management gates remain default-deny. No ambient Auth/Request user is substituted for a missing actor. Drain legacy queued model-bearing event payloads before upgrading and restart workers. Listen to canonical `FormChanged` / `FormEntryChanged` events; publication remains local source-after-commit, not an outbox.

Family 6 Composer metadata remains transitional until the coordinated suite major release.
