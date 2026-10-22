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
            $table->decimal('price', 10, 2)->nullable()->after('quantity');
            $table->boolean('is_gift')->default(false)->after('price');
            $table->foreignId('parent_id')->nullable()->after('is_gift')->constrained('order_carts')->cascadeOnDelete();
        });

        Schema::table('order_products', function (Blueprint $table) {
            $table->boolean('is_gift')->default(false)->after('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            $table->dropColumn('is_gift');
        });

        Schema::table('order_carts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['price', 'is_gift']);
        });
    }
};
