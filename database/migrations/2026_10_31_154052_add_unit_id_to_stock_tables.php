<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The stock and manufacturing tables that require a unit_id relation.
     *
     * @var array<int, string>
     */
    private array $tables = [
        'material_stocks',
        'product_recipe_stocks',
        'product_recipe_manufacturings',
        'manufacturing_lists',
        'manufacturing_recipes',
        'inventory_materials',
        'inventory_product_recipes',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (! Schema::hasColumn($tableName, 'unit_id')) {
                        $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'unit_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('unit_id');
                });
            }
        }
    }
};
