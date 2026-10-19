<?php

declare(strict_types=1);

namespace Nvl\Forms\Services;

use Illuminate\Container\Container;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Ownership\FormsOwnership;
use Nvl\Ownership\Services\OwnershipWriteLease;
use Nvl\Support\Ownership\Contracts\OwnershipBoundary;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;

/** Owns the narrow counter capability required by public Forms without granting management writes. @internal */
final readonly class FormCounterWriter
{
    public function __construct(private OwnershipBoundary $boundary, private Container $container) {}

    /** Increment one fixed statistic on a canonically locked native Form.
     * @param  array{last_used_at?:string,first_used_at?:string}  $extra
     */
    public function increment(Form $form, string $counter, array $extra = []): void
    {
        if (! in_array($counter, ['views_count', 'submissions_count', 'spam_count'], true)) {
            throw new OwnershipViolation('Unknown Forms bookkeeping counter.');
        }
        $this->boundary->assertRecord($form, 'forms.forms');
        if (! FormsOwnership::declared()) {
            $form->increment($counter, 1, $extra);

            return;
        }
        $connection = $form->getConnection();
        $connection->transaction(function () use ($form, $counter, $extra, $connection): void {
            $this->container->make(OwnershipWriteLease::class)->during($connection, function () use ($form, $counter, $extra): void {
                $canonical = FormModelRegistry::query()->whereKey($form->identifier())->lockForUpdate()->firstOrFail();
                $canonical->newQuery()->whereKey($canonical->identifier())->increment($counter, 1, $extra);
                $form->refresh();
            });
        });
    }
}
