<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_account_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('status', 32);
            $table->unsignedSmallInteger('progress')->default(0);
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_summary')->nullable();
            $table->timestampsTz();

            $table->index(['seller_account_id', 'status', 'created_at']);
        });

        Schema::create('sync_resource_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_account_id')->constrained()->cascadeOnDelete();
            $table->string('resource', 32);
            $table->string('status', 32)->default('pending');
            $table->string('availability', 32)->default('unknown');
            $table->text('cursor')->nullable();
            $table->unsignedInteger('processed_pages')->default(0);
            $table->timestampTz('last_attempt_at')->nullable();
            $table->timestampTz('last_success_at')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->timestampsTz();

            $table->unique(['seller_account_id', 'resource']);
            $table->index(['seller_account_id', 'last_success_at']);
        });

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sync_run_id')->constrained()->cascadeOnDelete();
            $table->string('source', 32);
            $table->string('resource', 32);
            $table->string('status', 32);
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->text('cursor_in')->nullable();
            $table->text('cursor_out')->nullable();
            $table->char('page_key', 64);
            $table->char('checksum', 64);
            $table->unsignedInteger('imported_count')->default(0);
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->timestampsTz();

            $table->unique(['sync_run_id', 'resource', 'page_key']);
            $table->index(['seller_account_id', 'resource', 'created_at']);
            $table->index(['sync_run_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
        Schema::dropIfExists('sync_resource_states');
        Schema::dropIfExists('sync_runs');
    }
};
