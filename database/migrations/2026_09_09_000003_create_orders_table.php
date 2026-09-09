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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shift_id')->nullable()->index();
            $table->foreignId('cashier_id')->nullable()->constrained('cashiers')->nullOnDelete();
            $table->foreignId('cashier_man_id')->nullable()->constrained('cashier_men')->nullOnDelete();
            $table->foreignId('hall_table_id')->nullable()->constrained('hall_tables')->nullOnDelete();
            $table->enum('module', ['takeaway', 'dinein', 'delivery']);
            $table->text('address')->nullable();
            $table->text('note')->nullable();
            $table->string('phone')->nullable();
            $table->string('name')->nullable();
            $table->boolean('is_pos')->default(true);
            $table->decimal('total', 12, 2);
            $table->decimal('total_tax', 12, 2)->default(0);
            $table->decimal('total_discount', 12, 2)->default(0);
            $table->decimal('final_price', 12, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
