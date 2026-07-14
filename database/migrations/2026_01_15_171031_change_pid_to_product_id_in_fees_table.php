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
        if (!Schema::hasColumn('fees', 'product_id')) {
            Schema::table('fees', function (Blueprint $table) {
                if (Schema::hasColumn('fees', 'pid')) {
                    $table->renameColumn('pid', 'product_id');
                } else {
                    $table->unsignedBigInteger('product_id')->nullable()->after('wid');
                    $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fees', function (Blueprint $table) {
            if (Schema::hasColumn('fees', 'product_id')) {
                $table->dropForeign(['product_id']);
                $table->dropColumn('product_id');
            }
        });
    }
};
