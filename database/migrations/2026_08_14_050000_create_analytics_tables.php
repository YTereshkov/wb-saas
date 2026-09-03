<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_account_id')->constrained()->cascadeOnDelete();
            $table->date('metric_date');
            $table->unsignedBigInteger('revenue_kopecks')->default(0);
            $table->unsignedInteger('orders')->default(0);
            $table->unsignedInteger('sales')->default(0);
            $table->unsignedInteger('returns')->default(0);
            $table->timestampsTz();

            $table->unique(['seller_account_id', 'metric_date']);
        });

        Schema::create('product_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_account_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->date('metric_date');
            $table->unsignedBigInteger('revenue_kopecks')->default(0);
            $table->unsignedInteger('orders')->default(0);
            $table->unsignedInteger('sales')->default(0);
            $table->unsignedInteger('returns')->default(0);
            $table->timestampsTz();

            $table->unique(['seller_account_id', 'product_id', 'metric_date']);
            $table->index(['seller_account_id', 'metric_date']);
            $table->index(['product_id', 'metric_date']);
            $table->foreign(['product_id', 'seller_account_id'])
                ->references(['id', 'seller_account_id'])
                ->on('products');
        });

        Schema::create('product_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_account_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->string('type', 48);
            $table->unsignedSmallInteger('priority');
            $table->string('severity', 24);
            $table->jsonb('evidence');
            $table->text('fact');
            $table->text('risk');
            $table->text('recommendation');
            $table->timestampTz('calculated_at');
            $table->unsignedBigInteger('data_revision');
            $table->timestampsTz();

            $table->unique(['seller_account_id', 'product_id']);
            $table->index(['seller_account_id', 'priority', 'calculated_at']);
            $table->foreign(['product_id', 'seller_account_id'])
                ->references(['id', 'seller_account_id'])
                ->on('products');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_signals');
        Schema::dropIfExists('product_daily_metrics');
        Schema::dropIfExists('account_daily_metrics');
    }
};
