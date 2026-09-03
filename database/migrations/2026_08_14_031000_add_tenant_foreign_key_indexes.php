<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->index(['active_seller_account_id', 'user_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index(['category_id', 'seller_account_id']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->index(['order_id', 'seller_account_id']);
        });

        Schema::table('return_facts', function (Blueprint $table) {
            $table->index(['sale_id', 'seller_account_id']);
        });

        Schema::table('stock_snapshots', function (Blueprint $table) {
            $table->index(['warehouse_id', 'seller_account_id']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_snapshots', function (Blueprint $table) {
            $table->dropIndex(['warehouse_id', 'seller_account_id']);
        });

        Schema::table('return_facts', function (Blueprint $table) {
            $table->dropIndex(['sale_id', 'seller_account_id']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['order_id', 'seller_account_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['category_id', 'seller_account_id']);
        });

        Schema::table('user_preferences', function (Blueprint $table) {
            $table->dropIndex(['active_seller_account_id', 'user_id']);
        });
    }
};
