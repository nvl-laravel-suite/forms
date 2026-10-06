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
        if (Schema::connection(PackageStorage::connection('forms'))->hasTable(FormsTables::get(FormsTables::I18n))) {
            throw new LogicException('Existing package table is not owned by this migration. Run nvl:doctor --strict and use nvl:schema:upgrade for a verified legacy installation.');
        }

        Schema::connection(PackageStorage::connection('forms'))->create(FormsTables::get(FormsTables::I18n), function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('form_id')
                ->constrained(FormsTables::get(FormsTables::Forms))
                ->cascadeOnDelete();
            $table->string('locale', 35);
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('submit_button_label', 100)->nullable();
            $table->string('success_title')->nullable();
            $table->text('success_message')->nullable();
            $table->json('content')->nullable();
            $table->timestampsTz();

            $table->unique(['form_id', 'locale']);
            $table->index(['locale', 'name']);
        });
    }

    public function down(): void
    {
        Schema::connection(PackageStorage::connection('forms'))->dropIfExists(FormsTables::get(FormsTables::I18n));
    }
};
