<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table): void {
            $table->text('fact')->nullable()->after('subject');
            $table->text('recommendation')->nullable()->after('fact');
            $table->unique(
                ['user_id', 'seller_account_id', 'product_signal_id', 'event', 'channel'],
                'notification_deliveries_signal_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table): void {
            $table->dropUnique('notification_deliveries_signal_unique');
            $table->dropColumn(['fact', 'recommendation']);
        });
    }
};
