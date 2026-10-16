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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate();
            $table->foreignId('saler_id')->nullable()->constrained('salers')->cascadeOnUpdate()->nullOnDelete();
            $table->decimal('total_cost', 12, 2)->default(0.00);
            $table->decimal('paid_amount', 12, 2)->default(0.00);
            $table->decimal('remaining_amount', 12, 2)->default(0.00);
            $table->decimal('total_quantity', 12, 2)->default(0.00);
            $table->string('receipt')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('material_id')->nullable()->constrained('materials')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('product_recipe_id')->nullable()->constrained('product_recipes')->cascadeOnUpdate()->nullOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->decimal('cost', 12, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
