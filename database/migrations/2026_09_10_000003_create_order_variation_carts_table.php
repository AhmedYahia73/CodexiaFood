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
        Schema::create('order_variation_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_cart_id')->constrained('order_carts')->cascadeOnDelete();
            $table->foreignId('variation_id')->constrained('variations')->cascadeOnDelete();
            $table->json('option_ids');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_variation_carts');
    }
};
