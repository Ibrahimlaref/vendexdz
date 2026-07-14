<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('rate_at_confirmation', 10, 2)->nullable()->after('quantity');
            $table->decimal('gerant_rate_at_confirmation', 10, 2)->nullable()->after('rate_at_confirmation');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['rate_at_confirmation', 'gerant_rate_at_confirmation']);
        });
    }
};
