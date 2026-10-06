<?php

declare(strict_types=1);

namespace Nvl\Forms\Actions\FormEntry;

use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;
use Nvl\Forms\Contracts\FormEntryPrivacyPolicy;
use Nvl\Forms\Contracts\RedactFormEntryContract;
use Nvl\Forms\Events\FormEntryChanged;
use Nvl\Forms\Models\FormEntry;
use Nvl\Support\Events\DomainEventDispatcher;

/**
 * Redacts selected personal-data fields without deleting the entry.
 *
 * @api
 */
final readonly class RedactFormEntryAction implements RedactFormEntryContract
{
    private const array REDACTABLE = [
        'subject',
        'email',
        'first_name',
        'last_name',
        'phone',
        'address',
        'body',
        'submission_data',
        'ip_address',
        'user_agent',
        'session_id',
    ];

    public function __construct(private FormEntryPrivacyPolicy $privacyPolicy, private DomainEventDispatcher $domainEvents) {}

    /**
     * @param  list<string>  $fields
     */
    public function execute(
        FormEntry|string $entry,
        array $fields,
        ?Authenticatable $actor = null,
    ): FormEntry {
        $unknown = array_values(array_diff($fields, self::REDACTABLE));
        if ($fields === [] || $unknown !== []) {
            throw new InvalidArgumentException('Redaction fields must use the documented allowlist.');
        }

        $updated = (new FormEntry)->getConnection()->transaction(function () use ($entry, $fields, $actor): FormEntry {
            $entryId = $entry instanceof FormEntry ? $entry->id : $entry;
            $model = FormEntry::query()
                ->with('form')
                ->lockForUpdate()
                ->findOrFail($entryId);
            $this->privacyPolicy->authorize('redact', $model, $actor);

            $attributes = array_fill_keys($fields, null);
            $attributes['redacted_at'] = now();
            $model->forceFill($attributes)->save();

            $this->domainEvents->dispatch(FormEntryChanged::for(
                $model->form,
                $model,
                'redacted',
                $actor,
                ['field_count' => count($fields)],
            ), $model->getConnection());

            return $model->refresh()->load('form');
        });

        return $updated;
    }
}
