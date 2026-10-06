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
        if (Schema::connection(PackageStorage::connection('forms'))->hasTable(FormsTables::get(FormsTables::Analytics))) {
            throw new LogicException('Existing package table is not owned by this migration. Run nvl:doctor --strict and use nvl:schema:upgrade for a verified legacy installation.');
        }

        Schema::connection(PackageStorage::connection('forms'))->create(FormsTables::get(FormsTables::Analytics), function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('form_id')
                ->comment('Reference to the parent form')
                ->constrained(FormsTables::get(FormsTables::Forms))
                ->onDelete('cascade');

            $table->string('event_type')
                ->comment('Type of event (view, submission, spam_blocked, etc.)');

            $table->string('origin')->nullable()
                ->comment('Origin domain where event occurred');

            $table->ipAddress('ip_address')->nullable()
                ->comment('IP address of the visitor');

            $table->text('user_agent')->nullable()
                ->comment('User agent string');

            $table->string('session_id')->nullable()
                ->comment('Session identifier');

            $table->json('metadata')->nullable()
                ->comment('Additional event metadata');

            $table->timestampsTz();

            // Indexes for performance and analytics
            $table->index('ip_address');
            $table->index(['form_id', 'event_type']);
            $table->index(['form_id', 'created_at']);
            $table->index(['event_type', 'created_at']);
            $table->index(['origin', 'created_at']);
            $table->index('created_at');
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
        Schema::connection(PackageStorage::connection('forms'))->dropIfExists(FormsTables::get(FormsTables::Analytics));
        Schema::connection(PackageStorage::connection('forms'))->enableForeignKeyConstraints();
    }
};
