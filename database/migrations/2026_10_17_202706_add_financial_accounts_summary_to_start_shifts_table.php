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
            $table->json('financial_accounts_summary')
                ->nullable()
                ->after('expenses_total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('start_shifts', function (Blueprint $table) {
            $table->dropColumn('financial_accounts_summary');
        });
    }
};
