<?php

declare(strict_types=1);

namespace Nvl\Forms\Actions\Form;

use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Nvl\Forms\Contracts\DeleteFormContract;
use Nvl\Forms\Events\FormChanged;
use Nvl\Forms\Models\Form;
use Nvl\Support\Events\DomainEventDispatcher;
use Throwable;

/**
 * Deletes a form and its associated data.
 *
 * @api
 */
final class DeleteFormAction implements DeleteFormContract
{
    /** Retain the source-aware domain event dispatcher. */
    public function __construct(private DomainEventDispatcher $domainEvents) {}

    /**
     * Execute the form deletion.
     *
     * @param  Form|string  $form  Form instance or identifier
     * @param  Authenticatable|null  $actor  Authenticated actor performing the deletion
     * @return bool Deletion success
     *
     * @throws Exception|Throwable If form not found or has dependencies
     */
    public function execute(Form|string $form, ?Authenticatable $actor = null): bool
    {
        // Resolve model if ID provided
        $form = $form instanceof Form
            ? $form
            : Form::findOrFail($form);

        $deleted = (new Form)->getConnection()->transaction(function () use ($form, $actor) {
            // Check for dependencies (form entries)
            if ($form->entries()->exists()) {
                throw new Exception((string) trans('nvl-forms::forms/messages.error.cannot_delete_with_entries'));
            }

            // Perform deletion
            $deleted = $form->delete();

            if ($deleted === null) {
                throw new Exception((string) trans('nvl-forms::forms/shared.messages.error.delete_failed', ['item' => (string) trans('nvl-forms::forms/general.entities.singular')]));
            }

            $this->domainEvents->dispatch(FormChanged::for($form, 'deleted', $actor), $form->getConnection());

            return $deleted;
        });

        return $deleted;
    }
}
