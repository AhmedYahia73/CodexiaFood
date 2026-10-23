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
        Schema::table('purchase_dues', function (Blueprint $table) {
            $table->decimal('payment', 12, 2)->default(0.00)->after('amount');
        });

        Schema::table('sale_dues', function (Blueprint $table) {
            $table->decimal('payment', 12, 2)->default(0.00)->after('amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_dues', function (Blueprint $table) {
            $table->dropColumn('payment');
        });

        Schema::table('sale_dues', function (Blueprint $table) {
            $table->dropColumn('payment');
        });
    }
};
