<?php

declare(strict_types=1);

namespace Nvl\Forms\Actions\FormEntry;

use Illuminate\Contracts\Auth\Authenticatable;
use Nvl\Forms\Events\FormEntryChanged;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormEntry;
use Nvl\Support\Events\DomainEventDispatcher;
use Throwable;

/**
 * Adds or updates a single security flag on a form entry.
 *
 * @internal
 */
final class AddFormEntrySecurityFlagAction
{
    /** Retain the source-aware domain event dispatcher. */
    public function __construct(private DomainEventDispatcher $domainEvents) {}

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
    public function execute(
        FormEntry|string $entry,
        string $key,
        mixed $value,
        ?Authenticatable $actor = null,
    ): FormEntry {
        /** @var array{entry: FormEntry, form: Form} $result */
        $result = (new FormEntry)->getConnection()->transaction(function () use ($entry, $key, $value, $actor): array {
            $entryId = $entry instanceof FormEntry ? $entry->id : $entry;
            $entryModel = FormEntry::query()->lockForUpdate()->findOrFail($entryId);

            $entryModel->setSecurityFlag($key, $value);
            $entryModel->save();

            $freshEntry = $entryModel->refresh()->load('form');
            $form = $freshEntry->form;

            $this->domainEvents->dispatch(FormEntryChanged::for(
                form: $form,
                entry: $freshEntry,
                operation: 'security_flag_added',
                actor: $actor,
                context: ['flag_key' => $key],
            ), $form->getConnection());

            return [
                'entry' => $freshEntry,
                'form' => $form->fresh() ?? $form,
            ];
        });

        return $result['entry'];
    }
}
