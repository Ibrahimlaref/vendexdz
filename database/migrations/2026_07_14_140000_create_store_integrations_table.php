<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->string('platform', 50)->index(); // shopify, youcan, woocommerce, etc.
            $table->string('name'); // Display name chosen by seller
            $table->string('external_url')->nullable(); // e.g. your-store.myshopify.com
            $table->uuid('webhook_uuid')->unique(); // Used in inbound webhook URL
            $table->text('webhook_secret'); // Encrypted secret for HMAC verification
            $table->json('platform_meta')->nullable(); // Platform-specific extra config
            $table->string('status', 30)->default('pending_webhook_setup');
            // status values: pending_webhook_setup, connected, error, disconnected
            $table->timestamp('last_webhook_received_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_id', 'platform', 'external_url'], 'store_platform_url_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_integrations');
    }
};
