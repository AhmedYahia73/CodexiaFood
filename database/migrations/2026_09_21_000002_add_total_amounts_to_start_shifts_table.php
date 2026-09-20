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
        Schema::table('start_shifts', function (Blueprint $table) {
            $table->decimal('default_total_amount', 12, 2)->default(0)->after('cashier_man_id');
            $table->decimal('total_mony', 12, 2)->nullable()->after('default_total_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('start_shifts', function (Blueprint $table) {
            $table->dropColumn(['default_total_amount', 'total_mony']);
        });
    }
};
