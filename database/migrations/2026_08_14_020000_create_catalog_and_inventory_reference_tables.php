<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_account_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('name');
            $table->timestampsTz();

            $table->unique(['id', 'seller_account_id']);
            $table->unique(['seller_account_id', 'external_id']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_account_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('nm_id');
            $table->string('vendor_code');
            $table->string('title');
            $table->string('brand')->nullable();
            $table->text('image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('external_updated_at')->nullable();
            $table->timestampsTz();

            $table->unique(['id', 'seller_account_id']);
            $table->unique(['seller_account_id', 'nm_id']);
            $table->index(['seller_account_id', 'vendor_code']);
            $table->index(['seller_account_id', 'is_active']);
            $table->foreign(['category_id', 'seller_account_id'])
                ->references(['id', 'seller_account_id'])
                ->on('categories');
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_account_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('name');
            $table->string('type', 64)->nullable();
            $table->timestampsTz();

            $table->unique(['id', 'seller_account_id']);
            $table->unique(['seller_account_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
