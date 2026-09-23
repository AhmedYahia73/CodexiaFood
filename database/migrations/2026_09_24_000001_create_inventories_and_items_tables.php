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
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->enum('status', ['pending', 'approve', 'reject'])->default('pending');
            $table->timestamps();
        });

        Schema::create('inventory_product_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained('inventories')->cascadeOnDelete();
            $table->foreignId('product_recipe_id')->constrained('product_recipes')->cascadeOnDelete();
            $table->decimal('stock', 12, 2)->default(0);
            $table->decimal('actual_stock', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('inventory_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained('inventories')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->decimal('stock', 12, 2)->default(0);
            $table->decimal('actual_stock', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_materials');
        Schema::dropIfExists('inventory_product_recipes');
        Schema::dropIfExists('inventories');
    }
};
