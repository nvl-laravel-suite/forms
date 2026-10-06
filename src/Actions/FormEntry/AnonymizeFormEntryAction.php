<?php

declare(strict_types=1);

namespace Nvl\Forms\Actions\FormEntry;

use Illuminate\Contracts\Auth\Authenticatable;
use Nvl\Forms\Contracts\AnonymizeFormEntryContract;
use Nvl\Forms\Contracts\FormEntryPrivacyPolicy;
use Nvl\Forms\Events\FormEntryChanged;
use Nvl\Forms\Models\FormEntry;
use Nvl\Support\Events\DomainEventDispatcher;

/**
 * Irreversibly removes all submitter-identifying data from an entry.
 *
 * @api
 */
final readonly class AnonymizeFormEntryAction implements AnonymizeFormEntryContract
{
    public function __construct(private FormEntryPrivacyPolicy $privacyPolicy, private DomainEventDispatcher $domainEvents) {}

    public function execute(
        FormEntry|string $entry,
        ?Authenticatable $actor = null,
    ): FormEntry {
        $updated = (new FormEntry)->getConnection()->transaction(function () use ($entry, $actor): FormEntry {
            $entryId = $entry instanceof FormEntry ? $entry->id : $entry;
            $model = FormEntry::query()
                ->with('form')
                ->lockForUpdate()
                ->findOrFail($entryId);
            $this->privacyPolicy->authorize('anonymize', $model, $actor);
            $model->forceFill([
                'subject' => null,
                'email' => null,
                'first_name' => null,
                'last_name' => null,
                'phone' => null,
                'address' => null,
                'body' => null,
                'submission_data' => null,
                'ip_address' => null,
                'user_agent' => null,
                'session_id' => null,
                'security_flags' => null,
                'redacted_at' => now(),
                'anonymized_at' => now(),
            ])->save();

            $this->domainEvents->dispatch(FormEntryChanged::for($model->form, $model, 'anonymized', $actor), $model->getConnection());

            return $model->refresh()->load('form');
        });

        return $updated;
    }
}
