<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests;

use Illuminate\Database\Schema\Blueprint;
use Nvl\Forms\Tests\Fixtures\PivotForm;

/** Uses the same Actions and inherited graph with an exclusive native owner pivot. */
abstract class PivotOwnershipTestCase extends OwnershipTestCase
{
    protected function formClass(): string
    {
        return PivotForm::class;
    }

    protected function prepareOwnershipSchema(): void
    {
        $root = new PivotForm;
        $root->getConnection()->getSchemaBuilder()->create('forms_owned_primary', static function (Blueprint $table) use ($root): void {
            $table->uuid('form_id')->primary();
            $table->unsignedBigInteger('company_id');
            $table->foreign('form_id')->references('id')->on($root->getTable())->cascadeOnDelete();
            $table->foreign('company_id')->references('id')->on('ownership_test_companies')->restrictOnDelete();
        });
    }
}
