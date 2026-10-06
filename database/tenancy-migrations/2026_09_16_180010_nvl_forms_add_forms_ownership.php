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

    /** Add canonical tenant identity to the complete Forms graph. */
    public function up(): void
    {
        foreach ([
            FormsTables::get(FormsTables::Forms),
            FormsTables::get(FormsTables::Entries),
            FormsTables::get(FormsTables::SubmissionReceipts),
            FormsTables::get(FormsTables::AllowedOrigins),
            FormsTables::get(FormsTables::Analytics),
            FormsTables::get(FormsTables::RateLimits),
            FormsTables::get(FormsTables::I18n),
        ] as $tableName) {
            if (! Schema::connection(PackageStorage::connection('forms'))->hasColumn($tableName, 'tenant_id')) {
                Schema::connection(PackageStorage::connection('forms'))->table($tableName, static function (Blueprint $table): void {
                    $table->uuid('tenant_id')->nullable();
                });
            }
        }

        Schema::connection(PackageStorage::connection('forms'))->table(FormsTables::get(FormsTables::Forms), static function (Blueprint $table): void {
            $table->dropUnique(['handle']);
            $table->unique(['tenant_id', 'handle'], 'forms_tenant_handle_unique');
            $table->index(['tenant_id', 'status', 'id'], 'forms_tenant_status_idx');
        });
        foreach ([FormsTables::get(FormsTables::Entries), FormsTables::get(FormsTables::SubmissionReceipts), FormsTables::get(FormsTables::AllowedOrigins), FormsTables::get(FormsTables::Analytics), FormsTables::get(FormsTables::RateLimits), FormsTables::get(FormsTables::I18n)] as $tableName) {
            Schema::connection(PackageStorage::connection('forms'))->table($tableName, static function (Blueprint $table): void {
                $table->index(['tenant_id', 'form_id'], 'forms_tenant_parent_'.substr(hash('sha256', $table->getTable()), 0, 8));
            });
        }
    }

    /** Preserve adopted ownership evidence. */
    public function down(): void {}
};
