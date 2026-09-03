<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_account_id')->constrained()->cascadeOnDelete();
            $table->string('event', 48);
            $table->string('channel', 24)->default('email');
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('threshold')->nullable();
            $table->string('frequency', 24)->nullable();
            $table->timestampsTz();

            $table->unique(['user_id', 'seller_account_id', 'event', 'channel'], 'notification_preferences_unique');
            $table->index(['seller_account_id', 'enabled']);
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_signal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 48);
            $table->string('channel', 24)->default('email');
            $table->string('status', 24)->default('pending');
            $table->string('subject');
            $table->string('action_url')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->timestampTz('queued_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampsTz();

            $table->index(['seller_account_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_preferences');
    }
};
