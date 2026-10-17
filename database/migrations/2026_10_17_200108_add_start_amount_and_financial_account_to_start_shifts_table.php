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
            $table->decimal('start_amount', 12, 2)->default(0)->after('cashier_man_id');
            $table->foreignId('financial_account_id')->nullable()->after('start_amount')->constrained('financial_accounts')->nullOnDelete();
            $table->decimal('orders_total', 12, 2)->default(0)->after('total_mony');
            $table->decimal('expenses_total', 12, 2)->default(0)->after('orders_total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('start_shifts', function (Blueprint $table) {
            $table->dropForeign(['financial_account_id']);
            $table->dropColumn(['start_amount', 'financial_account_id', 'orders_total', 'expenses_total']);
        });
    }
};
