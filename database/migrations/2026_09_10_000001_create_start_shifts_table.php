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
        Schema::create('start_shifts', function (Blueprint $table) {
            $table->id();
            $table->dateTime('start');
            $table->dateTime('end')->nullable();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('cashier_id')->constrained('cashiers')->cascadeOnDelete();
            $table->foreignId('cashier_man_id')->constrained('cashier_men')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('start_shifts');
    }
};
