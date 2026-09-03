<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('seller_account_id');
            $table->string('section', 48);
            $table->string('view_key', 48);
            $table->jsonb('filters');
            $table->jsonb('columns');
            $table->string('sort_column', 48);
            $table->string('sort_direction', 4);
            $table->unsignedSmallInteger('page_size');
            $table->timestampsTz();

            $table->unique(['user_id', 'seller_account_id', 'section', 'view_key']);
            $table->foreign(['seller_account_id', 'user_id'])
                ->references(['id', 'user_id'])
                ->on('seller_accounts')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_views');
    }
};
