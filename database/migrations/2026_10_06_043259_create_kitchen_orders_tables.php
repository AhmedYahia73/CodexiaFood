<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kitchen_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitchen_id')->constrained('kitchens')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->cascadeOnDelete();
            $table->foreignId('order_cart_id')->nullable()->constrained('order_carts')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('kitchen_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitchen_order_id')->constrained('kitchen_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('addon_id')->nullable()->constrained('addons')->nullOnDelete();
            $table->integer('quantity')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('kitchen_order_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitchen_order_item_id')->constrained('kitchen_order_items')->cascadeOnDelete();
            $table->json('option_ids');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_order_details');
        Schema::dropIfExists('kitchen_order_items');
        Schema::dropIfExists('kitchen_orders');
    }
};
