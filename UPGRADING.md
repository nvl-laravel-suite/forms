# Upgrading Forms to NVL Light

Replace one-operation Actions and their Contracts with `FormLifecycle`, `FormReader`, `FormSubmissions`, `FormEntries` and `FormAnalytics`. Pass native arrays and an explicit management actor. Package DTOs, HTTP controllers/routes/middleware, TypeScript registration, legacy event aliases and deprecated Tenancy integration are removed without compatibility aliases.

Public consumers call `FormSubmissions::submit($form, $fields, $protection)`. Its complete security and persistence workflow returns `FormEntry|FormSubmissionReceipt`. For a receipt, project `result_id`; do not expect a form entry for an external handler. Reuse the consumer fixtures for request/session extraction and response projection. Existing handler and callback ports now take explicit array context, never Request.

Keep revision checks and locale maps when adapting mutation DTOs. Use `translation_mode` patch/replace and pass `expected_revision` on update. Retain the supplied native Session when using CSRF, and the verified public site context when using native Ownership.

Default models load without Ownership installed. Native owned subclasses and canonical child inheritance remain supported. The deprecated bridge and automatic old-schema conversion are retired; released migration files and consumer records are retained. Existing storage adoption remains an explicit native Ownership operation with reviewed mappings.

Management gates remain default-deny. No ambient Auth/Request user is substituted for a missing actor. Drain legacy queued model-bearing event payloads before upgrading and restart workers. Listen to canonical `FormChanged` / `FormEntryChanged` events; publication remains local source-after-commit, not an outbox.

Use the coordinated 7.x peer dependency line. Bounded native exports retain the absolute-local-path API and reject unsupported remote disks or configured row/column/byte overflow. Export files now live beneath a Form-specific private directory. Exporting no longer deletes older files implicitly: configure the separate disabled-by-default `FormRetention` capability, explicit host Gate and positive ages before scheduling cleanup. Run both previews and destructive retention outside host transactions. Existing legacy flat exports require reviewed host cleanup. Entry legal holds and privacy policies still apply; no retention age is inferred from an existing installation.

`availableFrom` and `availableUntil` offsets are now converted to the application timezone before storage. Rows written earlier from offset input kept the input wall clock; review them if you supplied non-UTC offsets. `rateLimitPerHour` now rejects values above 2147483647.

`FormReader::options()` and `FormReader::suggestions()` now authorize the management actor through `nvl-forms.authorization.gate`. Pass the actor you already pass to `search()`: `$reader->options($filters)` becomes `$reader->options($filters, $actor)`, and `$reader->suggestions($query, $limit)` becomes `$reader->suggestions($query, $limit, $actor)`. Without an authorized actor, or inside a public owner-site context, both throw `AuthorizationException`; use `FormReader::get()` for public form resolution.

`FormEntries::update()` now rejects text fields that are not valid UTF-8 or contain NUL bytes, as public submission already did. Entries previously updated on PostgreSQL with NUL bytes were stored truncated at the first NUL. Submission origins whose host and port exceed 255 characters now fail origin validation. Pass forms and entries to services with their stored keys: a persisted model whose key was changed in memory now throws `ModelNotFoundException` instead of acting on a different record.

Forms 7.4.0 requires `nvl/core` ^7.4.0, and Core 7.4.0 conflicts with earlier Forms releases: upgrade both packages in the same `composer update`.
