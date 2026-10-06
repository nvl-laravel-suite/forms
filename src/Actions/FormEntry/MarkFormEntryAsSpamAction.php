<?php

declare(strict_types=1);

namespace Nvl\Forms\Actions\FormEntry;

use Illuminate\Contracts\Auth\Authenticatable;
use Nvl\Forms\Contracts\MarkFormEntryAsSpamContract;
use Nvl\Forms\Events\FormEntryChanged;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormEntry;
use Nvl\Support\Events\DomainEventDispatcher;
use Throwable;

/**
 * Marks a form entry as spam and updates aggregate counters.
 *
 * @api
 */
final class MarkFormEntryAsSpamAction implements MarkFormEntryAsSpamContract
{
    /** Retain the source-aware domain event dispatcher. */
    public function __construct(private DomainEventDispatcher $domainEvents) {}

    /**
     * Mark the given entry as spam.
     *
     * @param  FormEntry|string  $entry  Entry model or identifier
     * @param  string|null  $reason  Optional reason for spam classification
     * @return FormEntry Updated entry model
     *
     * @throws Throwable
     */
    public function execute(
        FormEntry|string $entry,
        ?string $reason = null,
        ?Authenticatable $actor = null,
    ): FormEntry {
        /** @var array{entry: FormEntry, form: Form} $result */
        $result = (new FormEntry)->getConnection()->transaction(function () use ($entry, $reason, $actor): array {
            $entryId = $entry instanceof FormEntry ? $entry->id : $entry;
            $entryModel = FormEntry::query()->lockForUpdate()->findOrFail($entryId);
            $form = Form::query()->lockForUpdate()->findOrFail($entryModel->form_id);
            $wasSpam = $entryModel->is_spam === true;

            $entryModel->setSecurityFlag('marked_spam_at', now()->toISOString());

            if ($reason !== null && $reason !== '') {
                $entryModel->setSecurityFlag('spam_reason', $reason);
            }

            $entryModel->update([
                'is_spam' => true,
            ]);

            if (! $wasSpam) {
                $form->increment('spam_count');
            }

            $freshEntry = $entryModel->refresh();
            $freshEntry->setRelation('form', $form);

            $this->domainEvents->dispatch(FormEntryChanged::for(
                form: $form,
                entry: $freshEntry,
                operation: 'marked_as_spam',
                actor: $actor,
                context: ['has_reason' => is_string($reason) && $reason !== ''],
            ), $form->getConnection());

            return [
                'entry' => $freshEntry,
                'form' => $form->fresh() ?? $form,
            ];
        });

        return $result['entry'];
    }
}
