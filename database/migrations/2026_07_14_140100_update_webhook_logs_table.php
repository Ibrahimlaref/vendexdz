<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('store_integration_id')->nullable()->after('sid');
            $table->string('platform', 50)->nullable()->after('source');
            $table->string('event_type', 100)->nullable()->after('platform');
            $table->boolean('signature_valid')->nullable()->after('status');
            $table->string('processing_result', 30)->nullable()->after('signature_valid');
            // processing_result: processed, failed, duplicate, unmatched
            $table->string('platform_order_id')->nullable()->after('processing_result');
            $table->integer('response_code')->nullable()->after('platform_order_id');

            $table->foreign('store_integration_id')
                ->references('id')
                ->on('store_integrations')
                ->onDelete('set null');

            $table->index('platform_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_logs', function (Blueprint $table) {
            $table->dropForeign(['store_integration_id']);
            $table->dropColumn([
                'store_integration_id',
                'platform',
                'event_type',
                'signature_valid',
                'processing_result',
                'platform_order_id',
                'response_code',
            ]);
        });
    }
};
