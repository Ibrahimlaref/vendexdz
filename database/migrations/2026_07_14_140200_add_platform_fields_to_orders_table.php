<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('platform_order_id')->nullable()->after('from');
            $table->unsignedBigInteger('store_integration_id')->nullable()->after('platform_order_id');

            $table->foreign('store_integration_id')
                ->references('id')
                ->on('store_integrations')
                ->onDelete('set null');

            // Idempotency: same external order from same integration can't be imported twice
            $table->unique(
                ['store_integration_id', 'platform_order_id'],
                'orders_integration_platform_order_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_integration_platform_order_unique');
            $table->dropForeign(['store_integration_id']);
            $table->dropColumn(['platform_order_id', 'store_integration_id']);
        });
    }
};
