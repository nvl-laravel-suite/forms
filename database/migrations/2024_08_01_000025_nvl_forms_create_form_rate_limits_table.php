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

    /**
     * Run the migrations.
     *
     * @throws RuntimeException
     */
    public function up(): void
    {
        if (Schema::connection(PackageStorage::connection('forms'))->hasTable(FormsTables::get(FormsTables::RateLimits))) {
            throw new LogicException('Existing package table is not owned by this migration. Run nvl:doctor --strict and use nvl:schema:upgrade for a verified legacy installation.');
        }

        Schema::connection(PackageStorage::connection('forms'))->create(FormsTables::get(FormsTables::RateLimits), function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('form_id')
                ->comment('Reference to the parent form')
                ->constrained(FormsTables::get(FormsTables::Forms))
                ->onDelete('cascade');

            $table->ipAddress('ip_address')
                ->comment('IP address being rate limited');

            $table->unsignedInteger('submission_count')->default(0)
                ->comment('Number of submissions in current time window');

            $table->timestampTz('window_start')
                ->comment('Start of the current rate limit window');

            $table->timestampTz('last_submission_at')
                ->comment('Timestamp of last submission attempt');

            $table->boolean('is_blocked')->default(false)
                ->comment('Whether IP is currently blocked');

            $table->timestampTz('blocked_until')->nullable()
                ->comment('Until when IP is blocked (null if not blocked)');

            $table->unsignedInteger('violation_count')->default(0)
                ->comment('Number of rate limit violations');

            $table->timestampsTz();

            // Indexes for performance
            $table->index('window_start');
            $table->index('blocked_until');
            $table->index('last_submission_at');

            // Unique constraint
            $table->unique(['form_id', 'ip_address']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @throws RuntimeException
     */
    public function down(): void
    {
        Schema::connection(PackageStorage::connection('forms'))->disableForeignKeyConstraints();
        Schema::connection(PackageStorage::connection('forms'))->dropIfExists(FormsTables::get(FormsTables::RateLimits));
        Schema::connection(PackageStorage::connection('forms'))->enableForeignKeyConstraints();
    }
};
