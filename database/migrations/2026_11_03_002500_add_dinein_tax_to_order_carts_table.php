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
            if (! Schema::hasColumn('order_carts', 'dinein_tax')) {
                $table->double('dinein_tax')->default(0)->after('discount_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_carts', function (Blueprint $table) {
            if (Schema::hasColumn('order_carts', 'dinein_tax')) {
                $table->dropColumn('dinein_tax');
            }
        });
    }
};
