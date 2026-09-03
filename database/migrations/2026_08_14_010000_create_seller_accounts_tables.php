<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('wb_account_id')->nullable();
            $table->string('source', 32)->default('demo');
            $table->string('status', 32)->default('pending');
            $table->string('timezone', 64)->default('Europe/Moscow');
            $table->unsignedBigInteger('data_revision')->default(0);
            $table->timestampTz('last_sync_started_at')->nullable();
            $table->timestampTz('last_sync_completed_at')->nullable();
            $table->timestampTz('disconnected_at')->nullable();
            $table->timestampsTz();

            $table->unique(['id', 'user_id']);
            $table->unique(['user_id', 'source', 'wb_account_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('seller_account_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_account_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();
            $table->text('token');
            $table->string('fingerprint', 64);
            $table->jsonb('permissions')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('invalidated_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('active_seller_account_id')->nullable();
            $table->string('period_preset', 32)->default('month');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->date('comparison_start')->nullable();
            $table->date('comparison_end')->nullable();
            $table->string('granularity', 16)->default('day');
            $table->timestampsTz();

            $table->foreign(['active_seller_account_id', 'user_id'])
                ->references(['id', 'user_id'])
                ->on('seller_accounts');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
        Schema::dropIfExists('seller_account_credentials');
        Schema::dropIfExists('seller_accounts');
    }
};
