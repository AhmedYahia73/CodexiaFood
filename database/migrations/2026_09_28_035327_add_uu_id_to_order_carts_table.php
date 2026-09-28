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
        Schema::table('order_carts', function (Blueprint $table) {
            if (! Schema::hasColumn('order_carts', 'uu_id')) {
                $table->string('uu_id')->nullable()->index()->after('hall_table_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_carts', function (Blueprint $table) {
            if (Schema::hasColumn('order_carts', 'uu_id')) {
                $table->dropColumn('uu_id');
            }
        });
    }
};
