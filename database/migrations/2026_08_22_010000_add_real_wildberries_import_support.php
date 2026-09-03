<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->string('region_name')->nullable()->after('type');
        });

        Schema::table('stock_snapshots', function (Blueprint $table) {
            $table->dropUnique([
                'seller_account_id',
                'product_id',
                'warehouse_id',
                'snapshot_at',
            ]);
            $table->string('variant_external_id')->default('0')->after('warehouse_id');
            $table->unique([
                'seller_account_id',
                'product_id',
                'warehouse_id',
                'variant_external_id',
                'snapshot_at',
            ]);
        });

        Schema::table('sync_resource_states', function (Blueprint $table) {
            $table->text('checkpoint')->nullable()->after('cursor');
        });

        Schema::create('raw_import_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_batch_id')->unique()->constrained()->cascadeOnDelete();
            $table->jsonb('payload');
            $table->timestampTz('retrieved_at');
            $table->timestampTz('expires_at');
            $table->timestampsTz();

            $table->index(['seller_account_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_import_pages');

        Schema::table('sync_resource_states', function (Blueprint $table) {
            $table->dropColumn('checkpoint');
        });

        Schema::table('stock_snapshots', function (Blueprint $table) {
            $table->dropUnique([
                'seller_account_id',
                'product_id',
                'warehouse_id',
                'variant_external_id',
                'snapshot_at',
            ]);
            $table->dropColumn('variant_external_id');
            $table->unique([
                'seller_account_id',
                'product_id',
                'warehouse_id',
                'snapshot_at',
            ]);
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn('region_name');
        });
    }
};
