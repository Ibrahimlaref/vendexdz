<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sheet_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores', 'id')->onDelete('cascade');
            $table->string('sheet_id');
            $table->string('status'); // success, failed, needs_review
            $table->integer('row_number')->nullable();
            $table->json('row_data')->nullable();
            $table->string('error_message', 1000)->nullable();
            $table->string('matched_field')->nullable(); // which field caused the review
            $table->foreignId('order_id')->nullable();
            $table->string('row_hash')->nullable()->index(); // for idempotency
            $table->string('order_reference')->nullable()->index(); // for idempotency
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sheet_sync_logs');
    }
};
