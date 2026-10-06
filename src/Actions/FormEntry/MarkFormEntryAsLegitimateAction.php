<?php

declare(strict_types=1);

namespace Nvl\Forms\Actions\FormEntry;

use Illuminate\Contracts\Auth\Authenticatable;
use Nvl\Forms\Contracts\MarkFormEntryAsLegitimateContract;
use Nvl\Forms\Events\FormEntryChanged;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormEntry;
use Nvl\Support\Events\DomainEventDispatcher;
use Throwable;

/**
 * Marks a form entry as legitimate and updates aggregate counters.
 *
 * @api
 */
final class MarkFormEntryAsLegitimateAction implements MarkFormEntryAsLegitimateContract
{
    /** Retain the source-aware domain event dispatcher. */
    public function __construct(private DomainEventDispatcher $domainEvents) {}

    /**
     * Mark the given entry as legitimate.
     *
     * @param  FormEntry|string  $entry  Entry model or identifier
     * @return FormEntry Updated entry model
     *
     * @throws Throwable
     */
    public function execute(FormEntry|string $entry, ?Authenticatable $actor = null): FormEntry
    {
        /** @var array{entry: FormEntry, form: Form, was_spam: bool} $result */
        $result = (new FormEntry)->getConnection()->transaction(function () use ($entry, $actor): array {
            $entryId = $entry instanceof FormEntry ? $entry->id : $entry;
            $entryModel = FormEntry::query()->lockForUpdate()->findOrFail($entryId);
            $form = Form::query()->lockForUpdate()->findOrFail($entryModel->form_id);

            $wasSpam = $entryModel->is_spam === true;

            $entryModel->setSecurityFlag('marked_legitimate_at', now()->toISOString());

            $entryModel->update([
                'is_spam' => false,
            ]);

            if ($wasSpam && $form->spam_count > 0) {
                $form->decrement('spam_count');
            }

            $freshEntry = $entryModel->refresh()->load('form');
            $form = $freshEntry->form;

            $this->domainEvents->dispatch(FormEntryChanged::for(
                form: $form,
                entry: $freshEntry,
                operation: 'marked_as_legitimate',
                actor: $actor,
                context: ['was_spam' => $wasSpam],
            ), $form->getConnection());

            return [
                'entry' => $freshEntry,
                'form' => $form->fresh() ?? $form,
                'was_spam' => $wasSpam,
            ];
        });

        return $result['entry'];
    }
}
