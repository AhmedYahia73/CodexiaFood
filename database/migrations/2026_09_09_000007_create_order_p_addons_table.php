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
        Schema::create('order_p_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('addon_id')->constrained('addons')->cascadeOnDelete();
            $table->foreignId('order_product_id')->constrained('order_products')->cascadeOnDelete();
            $table->decimal('price', 12, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_p_addons');
    }
};
