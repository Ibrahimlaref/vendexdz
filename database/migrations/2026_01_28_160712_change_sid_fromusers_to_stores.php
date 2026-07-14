<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Duplicate migration, already handled
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('sid');

            $table->unsignedBigInteger('sid')->nullable()->after('id');
            $table->foreign('sid')->references('id')->on('stores')->onDelete('cascade');
        });
    }
};
