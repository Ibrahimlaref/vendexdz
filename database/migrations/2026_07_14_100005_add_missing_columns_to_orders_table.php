<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'traking')) {
                $table->string('traking')->nullable()->change();
            }
            if (!Schema::hasColumn('orders', 'tracking')) {
                $table->string('tracking')->nullable()->after('oid');
            }
            if (!Schema::hasColumn('orders', 'aid')) {
                $table->unsignedBigInteger('aid')->nullable()->after('sid');
                $table->foreign('aid')->references('id')->on('users')->onDelete('cascade');
            }
            if (!Schema::hasColumn('orders', 'app_id')) {
                $table->unsignedBigInteger('app_id')->default(0)->after('aid');
            }
            if (!Schema::hasColumn('orders', 'from')) {
                $table->string('from')->default('system')->after('app_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'tracking')) {
                $table->dropColumn('tracking');
            }
            if (Schema::hasColumn('orders', 'aid')) {
                $table->dropForeign(['aid']);
                $table->dropColumn('aid');
            }
            if (Schema::hasColumn('orders', 'app_id')) {
                $table->dropColumn('app_id');
            }
            if (Schema::hasColumn('orders', 'from')) {
                $table->dropColumn('from');
            }
        });
    }
};
