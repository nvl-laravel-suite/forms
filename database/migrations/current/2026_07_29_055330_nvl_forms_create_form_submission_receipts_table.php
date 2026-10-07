<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Nvl\Forms\Definitions\Tables\FormsTables;
use Nvl\Support\Config\PackageStorage;

return new class extends Migration
{
    /** Use the effective package connection for Laravel's migration transaction. */
    public function getConnection(): ?string
    {
        return PackageStorage::connection('forms');
    }

    public function up(): void
    {
        if (Schema::connection(PackageStorage::connection('forms'))->hasTable(FormsTables::get(FormsTables::SubmissionReceipts))) {
            throw new LogicException('Existing package table is not owned by this migration. Run nvl:doctor --strict and use nvl:schema:upgrade for a verified legacy installation.');
        }

        Schema::connection(PackageStorage::connection('forms'))->create(FormsTables::get(FormsTables::SubmissionReceipts), function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('form_id')
                ->constrained(FormsTables::get(FormsTables::Forms))
                ->cascadeOnDelete();
            $table->string('idempotency_key', 128)->nullable();
            $table->string('payload_digest', 64);
            $table->string('registration_fingerprint', 64)->nullable();
            $table->string('state', 16);
            $table->string('result_id')->nullable();
            $table->timestamps();

            $table->unique(['form_id', 'idempotency_key'], 'nvl_forms_receipt_form_idempotency_unique');
            $table->unique(['form_id', 'registration_fingerprint'], 'nvl_forms_receipt_form_registration_unique');
            $table->index(['state', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::connection(PackageStorage::connection('forms'))->dropIfExists(FormsTables::get(FormsTables::SubmissionReceipts));
    }
};
