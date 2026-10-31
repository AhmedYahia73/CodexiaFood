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
        if (! Schema::hasTable('inventory_addons')) {
            Schema::create('inventory_addons', function (Blueprint $table) {
                $table->id();
                $table->foreignId('inventory_id')->constrained('inventories')->cascadeOnDelete();
                $table->foreignId('addon_id')->constrained('addons')->cascadeOnDelete();
                $table->decimal('stock', 12, 2)->default(0);
                $table->decimal('actual_stock', 12, 2)->default(0);
                $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_addons');
    }
};
