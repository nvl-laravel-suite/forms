<?php

declare(strict_types=1);

namespace Nvl\Forms\Services;

use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Nvl\Forms\Contracts\FormEntryDeletionPolicy;
use Nvl\Forms\Contracts\FormEntryPrivacyPolicy;
use Nvl\Forms\Events\FormChanged;
use Nvl\Forms\Events\FormEntryChanged;
use Nvl\Forms\Exceptions\FormException;
use Nvl\Forms\Exceptions\FormOwnershipException;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormEntry;
use Nvl\Support\Events\DomainEventDispatcher;
use Nvl\Support\Ownership\Contracts\OwnershipBoundary;
use Throwable;

/**
 * Native Forms workflow family.
 *
 * @api
 */
final class FormEntries
{
    public function __construct(private readonly FormEntryDeletionPolicy $deletionPolicy, private DomainEventDispatcher $domainEvents, private FormEntryPrivacyPolicy $privacyPolicy, private readonly FormEntryExportService $exportService, private readonly OwnershipBoundary $ownership) {}

    /**
     * Execute the form entry deletion.
     *
     * @param  FormEntry|string  $formEntry  Form entry instance or identifier
     * @param  Authenticatable|null  $actor  Actor performing the deletion
     * @return bool True if deletion was successful
     *
     * @throws Exception|Throwable If deletion fails or entry cannot be deleted
     */
    public function delete(FormEntry|string $formEntry, ?Authenticatable $actor = null): bool
    {
        FormManagement::authorize($actor);
        $entryId = $formEntry instanceof FormEntry ? $formEntry->id : $formEntry;
        /** @var array{deleted: bool, form: Form, entry: FormEntry, was_spam: bool} $result */
        $result = (new FormEntry)->getConnection()->transaction(function () use ($entryId, $actor) {
            $formEntry = FormEntry::query()->lockForUpdate()->findOrFail($entryId);
            $form = FormModelRegistry::query()->lockForUpdate()->findOrFail($formEntry->form_id);
            $formEntry->setRelation('form', $form);
            $this->deleteFormEntryValidateCanDelete($formEntry, $actor);
            $wasSpam = $formEntry->is_spam;
            $deleted = $formEntry->delete();
            if ($deleted !== true) {
                throw new Exception((string) trans('nvl-forms::forms/shared.messages.error.delete_failed', ['item' => (string) trans('nvl-forms::entries/general.entities.singular')]));
            }
            if ($wasSpam && $form->spam_count > 0) {
                $form->decrement('spam_count');
            } elseif (! $wasSpam && $form->submissions_count > 0) {
                $form->decrement('submissions_count');
            }
            $freshForm = $form->fresh();
            if (! $freshForm instanceof Form) {
                $freshForm = $form;
            }
            $this->domainEvents->dispatch(FormEntryChanged::for(form: $form, entry: $formEntry, operation: 'deleted', actor: $actor, context: ['was_spam' => $wasSpam]), $form->getConnection());

            return ['deleted' => $deleted, 'form' => $freshForm, 'entry' => $formEntry, 'was_spam' => $wasSpam];
        });

        return $result['deleted'];
    }

    /**
     * Validate that the entry can be deleted.
     *
     * @param  FormEntry  $formEntry  The form entry to validate
     *
     * @throws Exception If entry cannot be deleted
     */
    private function deleteFormEntryValidateCanDelete(FormEntry $formEntry, ?Authenticatable $actor = null): void
    {
        // Check if user has permission to delete this entry
        if ($actor !== null && method_exists($actor, 'can') && ! $actor->can('delete', $formEntry)) {
            throw new Exception((string) trans('nvl-forms::forms/shared.messages.error.permission_denied'));
        }
        $this->deletionPolicy->authorize($formEntry, $actor);
    }

    private const array REDACTABLE = ['subject', 'email', 'first_name', 'last_name', 'phone', 'address', 'body', 'submission_data', 'ip_address', 'user_agent', 'session_id'];

    /**
     * @param  list<string>  $fields
     */
    public function redact(FormEntry|string $entry, array $fields, ?Authenticatable $actor = null): FormEntry
    {
        FormManagement::authorize($actor);
        $unknown = array_values(array_diff($fields, self::REDACTABLE));
        if ($fields === [] || $unknown !== []) {
            throw new InvalidArgumentException('Redaction fields must use the documented allowlist.');
        }
        $updated = (new FormEntry)->getConnection()->transaction(function () use ($entry, $fields, $actor): FormEntry {
            $entryId = $entry instanceof FormEntry ? $entry->id : $entry;
            $model = FormEntry::query()->with('form')->lockForUpdate()->findOrFail($entryId);
            $this->privacyPolicy->authorize('redact', $model, $actor);
            $attributes = array_fill_keys($fields, null);
            $attributes['redacted_at'] = now();
            $model->forceFill($attributes)->save();
            $this->domainEvents->dispatch(FormEntryChanged::for($model->form, $model, 'redacted', $actor, ['field_count' => count($fields)]), $model->getConnection());

            return $model->refresh()->load('form');
        });

        return $updated;
    }

    public function anonymize(FormEntry|string $entry, ?Authenticatable $actor = null): FormEntry
    {
        FormManagement::authorize($actor);
        $updated = (new FormEntry)->getConnection()->transaction(function () use ($entry, $actor): FormEntry {
            $entryId = $entry instanceof FormEntry ? $entry->id : $entry;
            $model = FormEntry::query()->with('form')->lockForUpdate()->findOrFail($entryId);
            $this->privacyPolicy->authorize('anonymize', $model, $actor);
            $model->forceFill(['subject' => null, 'email' => null, 'first_name' => null, 'last_name' => null, 'phone' => null, 'address' => null, 'body' => null, 'submission_data' => null, 'ip_address' => null, 'user_agent' => null, 'session_id' => null, 'security_flags' => null, 'redacted_at' => now(), 'anonymized_at' => now()])->save();
            $this->domainEvents->dispatch(FormEntryChanged::for($model->form, $model, 'anonymized', $actor), $model->getConnection());

            return $model->refresh()->load('form');
        });

        return $updated;
    }

    /**
     * Persist a security flag key/value pair on the given entry.
     *
     * @param  FormEntry|string  $entry  Entry model or identifier
     * @param  string  $key  Security flag key
     * @param  mixed  $value  Security flag value
     * @return FormEntry Updated entry model
     *
     * @throws Throwable
     */
    public function flag(FormEntry|string $entry, string $key, mixed $value, ?Authenticatable $actor = null): FormEntry
    {
        FormManagement::authorize($actor);
        /** @var array{entry: FormEntry, form: Form} $result */
        $result = (new FormEntry)->getConnection()->transaction(function () use ($entry, $key, $value, $actor): array {
            $entryId = $entry instanceof FormEntry ? $entry->id : $entry;
            $entryModel = FormEntry::query()->lockForUpdate()->findOrFail($entryId);
            $entryModel->setSecurityFlag($key, $value);
            $entryModel->save();
            $freshEntry = $entryModel->refresh()->load('form');
            $form = $freshEntry->form;
            $this->domainEvents->dispatch(FormEntryChanged::for(form: $form, entry: $freshEntry, operation: 'security_flag_added', actor: $actor, context: ['flag_key' => $key]), $form->getConnection());

            return ['entry' => $freshEntry, 'form' => $form->fresh() ?? $form];
        });

        return $result['entry'];
    }

    /**
     * Mark the given entry as spam.
     *
     * @param  FormEntry|string  $entry  Entry model or identifier
     * @param  string|null  $reason  Optional reason for spam classification
     * @return FormEntry Updated entry model
     *
     * @throws Throwable
     */
    public function markSpam(FormEntry|string $entry, ?string $reason = null, ?Authenticatable $actor = null): FormEntry
    {
        FormManagement::authorize($actor);
        /** @var array{entry: FormEntry, form: Form} $result */
        $result = (new FormEntry)->getConnection()->transaction(function () use ($entry, $reason, $actor): array {
            $entryId = $entry instanceof FormEntry ? $entry->id : $entry;
            $entryModel = FormEntry::query()->lockForUpdate()->findOrFail($entryId);
            $form = FormModelRegistry::query()->lockForUpdate()->findOrFail($entryModel->form_id);
            $wasSpam = $entryModel->is_spam === true;
            $entryModel->setSecurityFlag('marked_spam_at', now()->toISOString());
            if ($reason !== null && $reason !== '') {
                $entryModel->setSecurityFlag('spam_reason', $reason);
            }
            $entryModel->update(['is_spam' => true]);
            if (! $wasSpam) {
                $form->increment('spam_count');
            }
            $freshEntry = $entryModel->refresh();
            $freshEntry->setRelation('form', $form);
            $this->domainEvents->dispatch(FormEntryChanged::for(form: $form, entry: $freshEntry, operation: 'marked_as_spam', actor: $actor, context: ['has_reason' => is_string($reason) && $reason !== '']), $form->getConnection());

            return ['entry' => $freshEntry, 'form' => $form->fresh() ?? $form];
        });

        return $result['entry'];
    }

    /**
     * Mark the given entry as legitimate.
     *
     * @param  FormEntry|string  $entry  Entry model or identifier
     * @return FormEntry Updated entry model
     *
     * @throws Throwable
     */
    public function markLegitimate(FormEntry|string $entry, ?Authenticatable $actor = null): FormEntry
    {
        FormManagement::authorize($actor);
        /** @var array{entry: FormEntry, form: Form, was_spam: bool} $result */
        $result = (new FormEntry)->getConnection()->transaction(function () use ($entry, $actor): array {
            $entryId = $entry instanceof FormEntry ? $entry->id : $entry;
            $entryModel = FormEntry::query()->lockForUpdate()->findOrFail($entryId);
            $form = FormModelRegistry::query()->lockForUpdate()->findOrFail($entryModel->form_id);
            $wasSpam = $entryModel->is_spam === true;
            $entryModel->setSecurityFlag('marked_legitimate_at', now()->toISOString());
            $entryModel->update(['is_spam' => false]);
            if ($wasSpam && $form->spam_count > 0) {
                $form->decrement('spam_count');
            }
            $freshEntry = $entryModel->refresh()->load('form');
            $form = $freshEntry->form;
            $this->domainEvents->dispatch(FormEntryChanged::for(form: $form, entry: $freshEntry, operation: 'marked_as_legitimate', actor: $actor, context: ['was_spam' => $wasSpam]), $form->getConnection());

            return ['entry' => $freshEntry, 'form' => $form->fresh() ?? $form, 'was_spam' => $wasSpam];
        });

        return $result['entry'];
    }

    /**
     * Execute the form entries export with permission checking and progress tracking.
     *
     * Validates permissions, cleans up old exports, fetches matching entries,
     * delegates CSV generation to the export service, saves the file, and logs activity.
     *
     * @param  Form|string  $form  Form instance or identifier
     * @param  array<string, mixed>  $options  Export options (date_from, date_to, limit, has_contact_info, include_submission_data, include_sensitive_data)
     * @param  Authenticatable|null  $actor  Authenticated actor requesting the export
     * @return string Absolute path to the generated CSV file
     *
     * @throws Exception When permission denied, no data to export, or export fails
     */
    public function export(Form|string $form, array $options = [], ?Authenticatable $actor = null): string
    {
        FormManagement::authorize($actor);
        $form = $form instanceof Form ? $form : FormModelRegistry::query()->findOrFail($form);
        $this->ownership->assertRecord($form, 'forms.forms');
        $this->exportFormEntriesValidateExportPermissions($form, $actor);
        $identity = 'exports/forms:'.($form->getConnection()->getName() ?? 'default');
        $partition = $this->ownership->key('forms.forms', $identity);
        $exportDirectory = $partition === $identity ? 'exports/forms' : 'exports/forms/'.hash('sha256', $partition);
        $this->exportFormEntriesCleanupOldExportFiles($exportDirectory);
        $entries = $this->exportFormEntriesGetEntriesToExport($form, $options);
        if ($entries->isEmpty()) {
            throw new Exception((string) trans('nvl-forms::forms/shared.messages.error.no_export_data', ['items' => (string) trans('nvl-forms::entries/general.entities.plural')]));
        }
        $rawActorId = $actor?->getAuthIdentifier();
        $actorId = is_string($rawActorId) || is_int($rawActorId) ? $rawActorId : null;
        $exportId = Str::ulid()->toString();
        $progressKey = $this->ownership->key('forms.forms', 'nvl:forms:export-progress:'.($actorId !== null ? (string) $actorId : 'guest').':'.$form->id.':'.$exportId);
        Cache::put($progressKey, ['status' => 'started', 'progress' => 0, 'total' => $entries->count(), 'started_at' => now()->toISOString()], now()->addHours());
        try {
            $csvContent = $this->exportService->generateCsvContent($form, $entries, $options);
            $filename = $this->exportService->generateFilename($form, $actorId);
            $path = $exportDirectory.'/'.$exportId.'_'.$filename;
            if (! Storage::disk('local')->put($path, $csvContent)) {
                throw new FormException('Unable to store the form entry export.');
            }
            Cache::put($progressKey, ['status' => 'completed', 'progress' => 100], now()->addHours());
            $this->domainEvents->dispatch(FormChanged::for(form: $form, operation: 'entries_exported', actor: $actor, context: ['entry_count' => $entries->count(), 'file_size' => strlen($csvContent)]), $form->getConnection());

            return Storage::disk('local')->path($path);
        } catch (Exception $e) {
            Cache::put($progressKey, ['status' => 'failed', 'error' => $e->getMessage()], now()->addHours());
            throw $e;
        }
    }

    /**
     * Query entries to export based on filter options.
     *
     * Supports date range filtering, limit, and contact info presence filtering.
     *
     * @param  Form  $form  The form to export entries from
     * @param  array<string, mixed>  $options  Export filter options
     * @return Collection<int, FormEntry>
     */
    private function exportFormEntriesGetEntriesToExport(Form $form, array $options): Collection
    {
        $query = $form->entries()->orderBy('created_at', 'desc');
        if (isset($options['date_from']) && is_string($options['date_from']) && $options['date_from'] !== '') {
            $query->whereDate('created_at', '>=', $options['date_from']);
        }
        if (isset($options['date_to']) && is_string($options['date_to']) && $options['date_to'] !== '') {
            $query->whereDate('created_at', '<=', $options['date_to']);
        }
        $limit = $options['limit'] ?? null;
        if (is_numeric($limit) && (int) $limit > 0) {
            $query->limit((int) $limit);
        }
        $hasContactInformation = filter_var($options['has_contact_info'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($hasContactInformation) {
            $query->whereRaw('(email IS NOT NULL OR phone IS NOT NULL)');
        }
        /** @var Collection<int, FormEntry> $result */
        $result = $query->get();

        return $result;
    }

    /**
     * Validate that the actor has permission to export entries.
     *
     * @param  Form  $form  The form being exported
     * @param  Authenticatable|null  $actor  The requesting actor
     *
     * @throws Exception When actor is null or lacks export permission
     */
    private function exportFormEntriesValidateExportPermissions(Form $form, ?Authenticatable $actor = null): void
    {
        if ($actor === null) {
            throw new Exception((string) trans('nvl-forms::forms/shared.messages.error.authentication_required'));
        }
        if (Gate::forUser($actor)->denies('export', $form)) {
            throw new Exception((string) trans('nvl-forms::forms/shared.messages.error.permission_denied'));
        }
    }

    /**
     * Delete export files older than 1 hour to prevent disk accumulation.
     */
    private function exportFormEntriesCleanupOldExportFiles(string $exportDir): void
    {
        try {
            /** @var array<int, string> $files */
            $files = Storage::disk('local')->files($exportDir);
            $cutoffTime = now()->subHour();
            foreach ($files as $file) {
                $lastModified = Storage::disk('local')->lastModified($file);
                if ($lastModified < $cutoffTime->timestamp) {
                    Storage::disk('local')->delete($file);
                }
            }
        } catch (Exception $e) {
            report($e);
        }
    }

    /**
     * Execute the filename generation.
     *
     * @param  Form  $form  Form model for export
     * @return string Generated filename
     */
    public function exportFilename(Form $form): string
    {
        return $this->exportService->generateFilename($form, null);
    }

    /**
     * Execute the form entry retrieval.
     *
     * @param  FormEntry|string  $formEntry  Form entry instance or identifier
     * @return FormEntry Form entry with loaded relationships
     */
    public function get(FormEntry|string $formEntry, ?Authenticatable $actor = null): FormEntry
    {
        FormManagement::authorize($actor);
        // Resolve model if ID provided
        $formEntry = FormEntry::query()->findOrFail($formEntry instanceof FormEntry ? $formEntry->identifier() : $formEntry);

        // Load all relationships needed for display
        return $formEntry->load(['form:id,handle']);
    }

    /**
     * Execute the form entries listing.
     *
     * @param  Form|string|null  $form  Form instance, identifier, or null for all entries
     * @param  bool  $paginate  Whether to paginate results
     * @param  int|null  $perPage  Items per page when paginating
     * @param  array<string,mixed>  $filters
     * @return LengthAwarePaginator<int, FormEntry>|Collection<int, FormEntry>
     *
     * @throws FormException When per_page parameter is invalid
     */
    public function paginate(Form|string|null $form = null, bool $paginate = true, ?int $perPage = null, array $filters = [], ?Authenticatable $actor = null): LengthAwarePaginator|Collection
    {
        FormManagement::authorize($actor);
        $query = FormEntry::query()->select($this->listFormEntriesGetSelectColumns())->with($this->listFormEntriesGetEagerLoadRelations());
        // Filter by specific form if provided
        if ($form !== null) {
            $formId = $form instanceof Form ? $form->id : $form;
            $query->where('form_id', $formId);
        }

        foreach (array_intersect_key($filters, array_flip(['subject', 'email', 'first_name', 'last_name', 'phone', 'submitted_from', 'ip_address', 'is_spam'])) as $column => $value) {
            $query->where($column, $value);
        }
        $query->orderByDesc('created_at')->orderBy('id');
        $perPage = $perPage !== null && $perPage > 0 ? $perPage : 20;
        if ($paginate) {
            return $query->paginate($perPage);
        }

        return $query->get();
    }

    /**
     * Get columns to select.
     *
     * @return array<string>
     */
    private function listFormEntriesGetSelectColumns(): array
    {
        return ['id', 'form_id', 'subject', 'email', 'first_name', 'last_name', 'phone', 'submitted_from', 'created_at'];
    }

    /**
     * Get relationships to eager load.
     *
     * @return array<string>
     */
    private function listFormEntriesGetEagerLoadRelations(): array
    {
        return ['form:id,handle'];
    }

    /**
     * Execute the ownership validation.
     *
     * @param  Form  $form  Form model
     * @param  FormEntry  $entry  Form entry model
     *
     * @throws FormOwnershipException If entry doesn't belong to form
     */
    public function validateOwnership(Form $form, FormEntry $entry): void
    {
        if ($entry->form_id !== $form->id) {
            throw new FormOwnershipException((string) trans('nvl-forms::forms/shared.messages.error.ownership_mismatch', ['item' => (string) trans('nvl-forms::entries/general.entities.singular'), 'parent' => (string) trans('nvl-forms::forms/general.entities.singular')]));
        }
    }

    /** @param array<string,mixed> $attributes */
    public function update(FormEntry|string $entry, array $attributes, ?Authenticatable $actor = null): FormEntry
    {
        FormManagement::authorize($actor);
        $allowed = ['subject', 'email', 'firstName', 'lastName', 'phone', 'address', 'body', 'submissionData'];
        if (array_diff(array_keys($attributes), $allowed) !== []) {
            throw new InvalidArgumentException('Entry updates accept only documented content fields.');
        }
        $validated = Validator::make($attributes, FormSubmissions::fieldRules())->validate();

        return (new FormEntry)->getConnection()->transaction(function () use ($entry, $validated, $actor): FormEntry {
            $model = FormEntry::query()->with('form')->lockForUpdate()->findOrFail($entry instanceof FormEntry ? $entry->identifier() : $entry);
            foreach ($validated as $key => $value) {
                $model->setAttribute(Str::snake($key), $value);
            }
            $model->save();
            $this->domainEvents->dispatch(FormEntryChanged::for($model->form, $model, 'updated', $actor), $model->getConnection());

            return $model->refresh()->load('form');
        });
    }
}
