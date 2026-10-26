# NVL forms events

This document describes the implemented source behavior. Acceptance is exercised by the owning package suites and Core committed-event regression tests; current release execution evidence is tracked in consumer-readiness.md. The authoritative machine-readable schema is [event-catalog.json](../resources/event-catalog.json), catalog version `1`. Event `schemaVersion` is independent of catalog version.

## Publication and listener timing

The native host dispatcher receives the captured event after the supplied source connection outer commit, or immediately when that connection has no active transaction.

Callbacks attach to the matching native connection and current nesting record; native outer/savepoint rollback discards the corresponding callbacks.

Missing source transaction records fail before commit; Mail Notifications reports and drops unusable observations.

Host after-commit listeners and queue after_commit policies can add their own deferral after publication. Host transaction infrastructure and dispatcher bindings are preserved.

Local callbacks are not an outbox. Process exit between commit and callback can lose delivery; no crash durability or exactly-once delivery is promised.

One canonical object is dispatched per qualifying producer call. This is local publication, not cross-process deduplication or a guarantee that repeated observations are unique.

Use Nvl\Support\Events\DomainEventDispatcher::dispatch($event, $writerConnection). Native Event::dispatch() is immediate and has no package interception.

## Payload security and no-op behavior

Scalar form/entry identifiers and bounded operation context. No submitted values, form/entry models or export file contents/path. Export observation includes count/size only.

Existing lifecycle/privacy mutation branches and export completion decide publication. Repeated explicit operations may publish again; these constructors do not provide idempotency.

Factories accept native Form/FormEntry/Authenticatable inputs only to snapshot scalar event fields; those input models are not retained.

Actor/owner identifiers do not grant access. Listeners must preserve the captured ownership and apply their own authorization when reading storage. Readonly payload fields and native value objects are schema facts; public constructors with mixed arrays do not create a new recursive sanitization boundary. Package producer shapes are documented below; hosts must not attach models, mutable service objects or private arbitrary data.

## Canonical events

| Event | Schema version | Trigger |
| --- | --- | --- |
| [FormChanged](#formchanged) | 1 | Form lifecycle or completed export observation. |
| [FormEntryChanged](#formentrychanged) | 1 | Entry lifecycle/privacy/security operation persisted. |

### FormChanged

`Nvl\Forms\Events\FormChanged` · [source](../src/Events/FormChanged.php) · event schema `1`.

Form lifecycle or completed export observation.

Constructor parameters, in native order:

| Parameter | Native PHP type | Visibility | Default | Collection shape |
| --- | --- | --- | --- | --- |
| `$formId` | `string` | public | `required` | — |
| `$operation` | `string` | public | `required` | — |
| `$actorId` | `string\|int\|null` | public | `null` | — |
| `$context` | `array` | public | `[]` | `array<string, bool\|float\|int\|string\|null>` |
| `$schemaVersion` | `int` | public | `1` | — |

Public payload fields:

| Field | Native PHP type | Collection shape |
| --- | --- | --- |
| `$formId` | `string` | — |
| `$operation` | `string` | — |
| `$actorId` | `string\|int\|null` | — |
| `$context` | `array` | `array<string, bool\|float\|int\|string\|null>` |
| `$schemaVersion` | `int` | — |

Producers and exact scheduling connection expressions (variables are resolved in the linked source):

| Producer | Source connection |
| --- | --- |
| [Services/FormLifecycle.php](../src/Services/FormLifecycle.php) | `$form->getConnection()` |
| [Services/FormLifecycle.php](../src/Services/FormLifecycle.php) | `$form->getConnection()` |
| [Services/FormLifecycle.php](../src/Services/FormLifecycle.php) | `$newForm->getConnection()` |
| [Services/FormLifecycle.php](../src/Services/FormLifecycle.php) | `$form->getConnection()` |
| [Services/FormEntries.php](../src/Services/FormEntries.php) | `$form->getConnection()` |


### FormEntryChanged

`Nvl\Forms\Events\FormEntryChanged` · [source](../src/Events/FormEntryChanged.php) · event schema `1`.

Entry lifecycle/privacy/security operation persisted.

Constructor parameters, in native order:

| Parameter | Native PHP type | Visibility | Default | Collection shape |
| --- | --- | --- | --- | --- |
| `$formId` | `string` | public | `required` | — |
| `$entryId` | `string` | public | `required` | — |
| `$operation` | `string` | public | `required` | — |
| `$actorId` | `string\|int\|null` | public | `null` | — |
| `$context` | `array` | public | `[]` | `array<string, bool\|float\|int\|string\|null>` |
| `$schemaVersion` | `int` | public | `1` | — |

Public payload fields:

| Field | Native PHP type | Collection shape |
| --- | --- | --- |
| `$formId` | `string` | — |
| `$entryId` | `string` | — |
| `$operation` | `string` | — |
| `$actorId` | `string\|int\|null` | — |
| `$context` | `array` | `array<string, bool\|float\|int\|string\|null>` |
| `$schemaVersion` | `int` | — |

Producers and exact scheduling connection expressions (variables are resolved in the linked source):

| Producer | Source connection |
| --- | --- |
| [Services/FormEntries.php](../src/Services/FormEntries.php) | `$form->getConnection()` |
| [Services/FormEntries.php](../src/Services/FormEntries.php) | `$model->getConnection()` |
| [Services/FormSubmissions.php](../src/Services/FormSubmissions.php) | `$entry->getConnection()` |
| [Services/FormEntries.php](../src/Services/FormEntries.php) | `$form->getConnection()` |
| [Services/FormEntries.php](../src/Services/FormEntries.php) | `$form->getConnection()` |
| [Services/FormEntries.php](../src/Services/FormEntries.php) | `$form->getConnection()` |
| [Services/FormEntries.php](../src/Services/FormEntries.php) | `$model->getConnection()` |


## Consumer event assertions

Use canonical FormChanged and FormEntryChanged classes with Laravel event fakes and listeners. Legacy aliases and listener bridges are removed. Drain old queued model-bearing payloads and restart workers before upgrade. Source commit, rollback, native ownership and sanitized payload behavior remain covered by the retained package event and Ownership suites.
