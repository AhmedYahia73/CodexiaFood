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
        Schema::create('order_carts', function (Blueprint $table) {
            $table->id();
            $table->enum('module', ['takeaway', 'dinein', 'delivery']);
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('cashier_id')->constrained('cashiers')->cascadeOnDelete();
            $table->foreignId('cashier_man_id')->nullable()->constrained('cashier_men')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->integer('quantity')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_carts');
    }
};
