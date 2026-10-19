<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Nvl\Forms\Tests\Fixtures\PivotForm;

it('uses native primary pivots across duplicate owner-local identities and inherited copy', function (): void {
    $roots = [];
    foreach ([7, 8] as $id) {
        $roots[$id] = $this->owned($id, fn () => $this->createOwnedForm());
    }
    $this->owned(7, function () use ($roots): void {
        expect(PivotForm::count())->toBe(1)->and(PivotForm::firstOrFail()->id)->toBe($roots[7]->id);
        expect(fn () => $roots[7]->getConnection()->table('forms_owned_primary')->where('form_id', $roots[7]->id)->update(['company_id' => 8]))->toThrow(QueryException::class);
    });
});
