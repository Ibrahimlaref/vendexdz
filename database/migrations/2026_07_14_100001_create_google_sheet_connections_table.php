<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_sheet_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sid')->constrained('stores', 'id')->onDelete('cascade');
            $table->string('sheet_id');
            $table->json('column_mapping')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('mapping_confirmed')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->integer('sync_interval_minutes')->default(10);
            $table->integer('last_synced_row')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_sheet_connections');
    }
};
