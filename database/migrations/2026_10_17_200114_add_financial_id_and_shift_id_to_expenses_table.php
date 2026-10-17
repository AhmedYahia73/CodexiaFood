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
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('financial_id')->nullable()->after('expense_list_id')->constrained('financial_accounts')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->after('cashier_man_id')->constrained('start_shifts')->nullOnDelete();
            $table->text('description')->nullable()->after('amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['financial_id']);
            $table->dropForeign(['shift_id']);
            $table->dropColumn(['financial_id', 'shift_id', 'description']);
        });
    }
};
