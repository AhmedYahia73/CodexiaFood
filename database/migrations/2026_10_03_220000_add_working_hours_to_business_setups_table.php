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
        Schema::table('business_setups', function (Blueprint $table) {
            if (! Schema::hasColumn('business_setups', 'start_day')) {
                $table->time('start_day')->default('09:00:00')->after('branch_cover');
            }
            if (! Schema::hasColumn('business_setups', 'end_day')) {
                $table->time('end_day')->default('03:00:00')->after('start_day');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_setups', function (Blueprint $table) {
            if (Schema::hasColumn('business_setups', 'end_day')) {
                $table->dropColumn('end_day');
            }
            if (Schema::hasColumn('business_setups', 'start_day')) {
                $table->dropColumn('start_day');
            }
        });
    }
};
