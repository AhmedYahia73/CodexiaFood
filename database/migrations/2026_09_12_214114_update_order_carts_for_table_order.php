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
            $table->string('module')->change();
            $table->foreignId('cashier_id')->nullable()->change();

            if (! Schema::hasColumn('order_carts', 'hall_table_id')) {
                $table->foreignId('hall_table_id')->nullable()->after('branch_id')->constrained('hall_tables')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_carts', function (Blueprint $table) {
            if (Schema::hasColumn('order_carts', 'hall_table_id')) {
                $table->dropConstrainedForeignId('hall_table_id');
            }
        });
    }
};
