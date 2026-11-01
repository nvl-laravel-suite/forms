# Upgrading Forms to NVL Light

Replace one-operation Actions and their Contracts with `FormLifecycle`, `FormReader`, `FormSubmissions`, `FormEntries` and `FormAnalytics`. Pass native arrays and an explicit management actor. Package DTOs, HTTP controllers/routes/middleware, TypeScript registration, legacy event aliases and deprecated Tenancy integration are removed without compatibility aliases.

Public consumers call `FormSubmissions::submit($form, $fields, $protection)`. Its complete security and persistence workflow returns `FormEntry|FormSubmissionReceipt`. For a receipt, project `result_id`; do not expect a form entry for an external handler. Reuse the consumer fixtures for request/session extraction and response projection. Existing handler and callback ports now take explicit array context, never Request.

Keep revision checks and locale maps when adapting mutation DTOs. Use `translation_mode` patch/replace and pass `expected_revision` on update. Retain the supplied native Session when using CSRF, and the verified public site context when using native Ownership.

Default models load without Ownership installed. Native owned subclasses and canonical child inheritance remain supported. The deprecated bridge and automatic old-schema conversion are retired; released migration files and consumer records are retained. Existing storage adoption remains an explicit native Ownership operation with reviewed mappings.

Management gates remain default-deny. No ambient Auth/Request user is substituted for a missing actor. Drain legacy queued model-bearing event payloads before upgrading and restart workers. Listen to canonical `FormChanged` / `FormEntryChanged` events; publication remains local source-after-commit, not an outbox.

Use the coordinated 7.x peer dependency line. Bounded native exports retain the absolute-local-path API and reject unsupported remote disks or configured row/column/byte overflow. Export files now live beneath a Form-specific private directory. Exporting no longer deletes older files implicitly: configure the separate disabled-by-default `FormRetention` capability, explicit host Gate and positive ages before scheduling cleanup. Run both previews and destructive retention outside host transactions. Existing legacy flat exports require reviewed host cleanup. Entry legal holds and privacy policies still apply; no retention age is inferred from an existing installation.
