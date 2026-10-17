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
        Schema::table('expense_lists', function (Blueprint $table) {
            $table->foreignId('financial_id')->nullable()->after('description')->constrained('financial_accounts')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expense_lists', function (Blueprint $table) {
            $table->dropForeign(['financial_id']);
            $table->dropColumn('financial_id');
        });
    }
};
