<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_account_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->string('srid');
            $table->timestampTz('ordered_at');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('amount_kopecks');
            $table->string('status', 64);
            $table->boolean('is_cancelled')->default(false);
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('external_updated_at')->nullable();
            $table->timestampsTz();

            $table->unique(['id', 'seller_account_id']);
            $table->unique(['seller_account_id', 'srid']);
            $table->index(['seller_account_id', 'ordered_at']);
            $table->index(['product_id', 'ordered_at']);
            $table->foreign(['product_id', 'seller_account_id'])
                ->references(['id', 'seller_account_id'])
                ->on('products');
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_account_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('external_id');
            $table->string('srid')->nullable();
            $table->timestampTz('sold_at');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('amount_kopecks');
            $table->timestampTz('external_updated_at')->nullable();
            $table->timestampsTz();

            $table->unique(['id', 'seller_account_id']);
            $table->unique(['seller_account_id', 'external_id']);
            $table->index(['seller_account_id', 'sold_at']);
            $table->index(['product_id', 'sold_at']);
            $table->index(['seller_account_id', 'srid']);
            $table->foreign(['product_id', 'seller_account_id'])
                ->references(['id', 'seller_account_id'])
                ->on('products');
            $table->foreign(['order_id', 'seller_account_id'])
                ->references(['id', 'seller_account_id'])
                ->on('orders');
        });

        Schema::create('return_facts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_account_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->string('external_id');
            $table->timestampTz('returned_at');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('amount_kopecks');
            $table->timestampTz('external_updated_at')->nullable();
            $table->timestampsTz();

            $table->unique(['seller_account_id', 'external_id']);
            $table->index(['seller_account_id', 'returned_at']);
            $table->index(['product_id', 'returned_at']);
            $table->foreign(['product_id', 'seller_account_id'])
                ->references(['id', 'seller_account_id'])
                ->on('products');
            $table->foreign(['sale_id', 'seller_account_id'])
                ->references(['id', 'seller_account_id'])
                ->on('sales');
        });

        Schema::create('stock_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_account_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->timestampTz('snapshot_at');
            $table->date('snapshot_date');
            $table->unsignedInteger('quantity');
            $table->timestampsTz();

            $table->unique([
                'seller_account_id',
                'product_id',
                'warehouse_id',
                'snapshot_at',
            ]);
            $table->index(['seller_account_id', 'snapshot_date']);
            $table->index(['product_id', 'warehouse_id', 'snapshot_date']);
            $table->foreign(['product_id', 'seller_account_id'])
                ->references(['id', 'seller_account_id'])
                ->on('products');
            $table->foreign(['warehouse_id', 'seller_account_id'])
                ->references(['id', 'seller_account_id'])
                ->on('warehouses');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_snapshots');
        Schema::dropIfExists('return_facts');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('orders');
    }
};
