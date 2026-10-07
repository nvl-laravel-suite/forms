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
| [Actions/Form/CreateFormAction.php](../src/Actions/Form/CreateFormAction.php) | `$form->getConnection()` |
| [Actions/Form/DeleteFormAction.php](../src/Actions/Form/DeleteFormAction.php) | `$form->getConnection()` |
| [Actions/Form/DuplicateFormAction.php](../src/Actions/Form/DuplicateFormAction.php) | `$newForm->getConnection()` |
| [Actions/Form/UpdateFormAction.php](../src/Actions/Form/UpdateFormAction.php) | `$form->getConnection()` |
| [Actions/FormEntry/ExportFormEntriesAction.php](../src/Actions/FormEntry/ExportFormEntriesAction.php) | `$form->getConnection()` |

Deprecated alias: `Nvl\Forms\Events\FormChangedEvent` ([shim](../src/Events/FormChangedEvent.php)). It is the same canonical class with this constructor.

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
| [Actions/FormEntry/AddFormEntrySecurityFlagAction.php](../src/Actions/FormEntry/AddFormEntrySecurityFlagAction.php) | `$form->getConnection()` |
| [Actions/FormEntry/AnonymizeFormEntryAction.php](../src/Actions/FormEntry/AnonymizeFormEntryAction.php) | `$model->getConnection()` |
| [Actions/FormEntry/CreateFormEntryAction.php](../src/Actions/FormEntry/CreateFormEntryAction.php) | `$entry->getConnection()` |
| [Actions/FormEntry/DeleteFormEntryAction.php](../src/Actions/FormEntry/DeleteFormEntryAction.php) | `$form->getConnection()` |
| [Actions/FormEntry/MarkFormEntryAsLegitimateAction.php](../src/Actions/FormEntry/MarkFormEntryAsLegitimateAction.php) | `$form->getConnection()` |
| [Actions/FormEntry/MarkFormEntryAsSpamAction.php](../src/Actions/FormEntry/MarkFormEntryAsSpamAction.php) | `$form->getConnection()` |
| [Actions/FormEntry/RedactFormEntryAction.php](../src/Actions/FormEntry/RedactFormEntryAction.php) | `$model->getConnection()` |

Deprecated alias: `Nvl\Forms\Events\FormEntryChangedEvent` ([shim](../src/Events/FormEntryChangedEvent.php)). It is the same canonical class with this constructor.

## Major 5 listener and queue migration

Legacy names are deprecated for major 5 and removed no earlier than major 6. class_alias preserves imports, instanceof, listener type hints and the new versioned constructor, not the former model-bearing API.

The native Laravel exact-listener bridge reads getRawListeners at delivery time and prepares legacy listeners through makeListener; strict-identical canonical registrations are skipped. Late registration, subscribers, cached discovery and queued listener preparation use the native dispatcher seams covered by Core’s committed-event and native host transaction regressions.

One canonical object is emitted once. Native wildcard/interface listeners see the canonical event once; suffix-specific *Event wildcards must migrate. The bridge does not redispatch a legacy string.

Drain old queued model-bearing payloads and restart workers before upgrade. New serialized alias instances restore the canonical versioned class without model restoration.

Event fakes/filters and assertions use canonical class names. EventAliases::canonicalName() helps migrate old names; old fake filters are not automatically rewritten.

Host custom dispatchers are retained. Explicit EventAliases::listen($event, $listener) maps through the canonical adapter; absent native bridging/host adapter, Doctor reports the deprecated-name limitation.

```php
$aliases = app(\Nvl\Support\Events\EventAliases::class);
$canonical = $aliases->canonicalName($legacyClass);
$aliases->listen($canonical, $listener);
```

## Deferred acceptance checks

Final testing must compare catalog types/defaults/aliases with actual classes, recursively inspect producer payloads, and prove source outer commit, nested rollback, unrelated connection independence and retry behavior without an uncommitted test-harness transaction. Where applicable it must cover legacy exact/cached/queued listeners, canonical fakes and wildcard delivery, tenant capture, package no-op guards and observational failure containment. This document does not report those checks as passing.

## Consumer event assertions

Use the canonical event class listed in the catalog for `Event::fake([...])` and `Event::assertDispatched(...)`. Laravel fake filters compare the emitted class name; an old alias import does not rename that canonical object. Legacy exact listeners are bridged at delivery time through Laravel’s native dispatcher. Keep compatibility listener tests on their exact legacy name, and migrate suffix-specific wildcards to canonical names.

