<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceProductForeignKey('product_daily_metrics', true);
        $this->replaceProductForeignKey('product_signals', true);
    }

    public function down(): void
    {
        $this->replaceProductForeignKey('product_signals', false);
        $this->replaceProductForeignKey('product_daily_metrics', false);
    }

    private function replaceProductForeignKey(string $tableName, bool $cascade): void
    {
        Schema::table($tableName, function (Blueprint $table) use ($cascade): void {
            $table->dropForeign(['product_id', 'seller_account_id']);
            $foreign = $table->foreign(['product_id', 'seller_account_id'])
                ->references(['id', 'seller_account_id'])
                ->on('products');

            if ($cascade) {
                $foreign->cascadeOnDelete();
            }
        });
    }
};
