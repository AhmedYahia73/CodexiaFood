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
        Schema::table('business_setups', function (Blueprint $table) {
            $table->boolean('has_gift')->default(false)->after('table_order_url');
            $table->integer('price_gift')->default(1)->after('has_gift');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_setups', function (Blueprint $table) {
            $table->dropColumn(['has_gift', 'price_gift']);
        });
    }
};
