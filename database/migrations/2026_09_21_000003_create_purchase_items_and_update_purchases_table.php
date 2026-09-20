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
        Schema::table('purchases', function (Blueprint $table) {
            if (! Schema::hasColumn('purchases', 'total_cost')) {
                $table->decimal('total_cost', 12, 2)->default(0)->after('receipt');
            }
            if (! Schema::hasColumn('purchases', 'total_quantity')) {
                $table->decimal('total_quantity', 12, 2)->default(0)->after('total_cost');
            }
            if (! Schema::hasColumn('purchases', 'notes')) {
                $table->text('notes')->nullable()->after('total_quantity');
            }
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
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
        Schema::dropIfExists('purchase_items');

        Schema::table('purchases', function (Blueprint $table) {
            $columnsToDrop = [];
            if (Schema::hasColumn('purchases', 'total_cost')) {
                $columnsToDrop[] = 'total_cost';
            }
            if (Schema::hasColumn('purchases', 'total_quantity')) {
                $columnsToDrop[] = 'total_quantity';
            }
            if (Schema::hasColumn('purchases', 'notes')) {
                $columnsToDrop[] = 'notes';
            }
            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
