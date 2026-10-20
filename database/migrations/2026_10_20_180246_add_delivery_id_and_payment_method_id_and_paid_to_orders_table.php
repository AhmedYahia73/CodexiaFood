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
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('delivery_id')
                ->nullable()
                ->after('hall_table_id')
                ->constrained('deliveries')
                ->nullOnDelete();

            $table->foreignId('payment_method_id')
                ->nullable()
                ->after('financial_account_id')
                ->constrained('payment_methods')
                ->nullOnDelete();

            $table->boolean('paid')->default(true)->after('is_pos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_id');
            $table->dropConstrainedForeignId('payment_method_id');
            $table->dropColumn('paid');
        });
    }
};
