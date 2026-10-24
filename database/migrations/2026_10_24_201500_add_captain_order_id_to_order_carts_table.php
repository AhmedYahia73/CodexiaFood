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
            if (! Schema::hasColumn('order_carts', 'captain_order_id')) {
                $table->foreignId('captain_order_id')
                    ->nullable()
                    ->after('hall_table_id')
                    ->constrained('captain_orders')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_carts', function (Blueprint $table) {
            if (Schema::hasColumn('order_carts', 'captain_order_id')) {
                $table->dropConstrainedForeignId('captain_order_id');
            }
        });
    }
};
