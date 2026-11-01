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
            if (! Schema::hasColumn('order_carts', 'discount_id')) {
                $table->foreignId('discount_id')->nullable()->after('product_id')->constrained('discounts')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_carts', function (Blueprint $table) {
            if (Schema::hasColumn('order_carts', 'discount_id')) {
                $table->dropConstrainedForeignId('discount_id');
            }
        });
    }
};
