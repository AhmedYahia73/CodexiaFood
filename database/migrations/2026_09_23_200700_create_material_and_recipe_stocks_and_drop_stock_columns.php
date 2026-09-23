<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('material_stocks')) {
            Schema::create('material_stocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->decimal('stock', 12, 2)->default(0);
                $table->timestamps();

                $table->unique(['material_id', 'branch_id']);
            });
        }

        if (! Schema::hasTable('product_recipe_stocks')) {
            Schema::create('product_recipe_stocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_recipe_id')->constrained('product_recipes')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->decimal('stock', 12, 2)->default(0);
                $table->timestamps();

                $table->unique(['product_recipe_id', 'branch_id']);
            });
        }

        // Migrate existing stock data to the first branch if available
        $firstBranch = DB::table('branches')->first();
        if ($firstBranch) {
            if (Schema::hasColumn('materials', 'stock')) {
                $materials = DB::table('materials')->where('stock', '>', 0)->get();
                foreach ($materials as $material) {
                    DB::table('material_stocks')->updateOrInsert(
                        ['material_id' => $material->id, 'branch_id' => $firstBranch->id],
                        ['stock' => $material->stock, 'created_at' => now(), 'updated_at' => now()]
                    );
                }
            }

            if (Schema::hasColumn('product_recipes', 'stock')) {
                $recipes = DB::table('product_recipes')->where('stock', '>', 0)->get();
                foreach ($recipes as $recipe) {
                    DB::table('product_recipe_stocks')->updateOrInsert(
                        ['product_recipe_id' => $recipe->id, 'branch_id' => $firstBranch->id],
                        ['stock' => $recipe->stock, 'created_at' => now(), 'updated_at' => now()]
                    );
                }
            }
        }

        // Drop stock columns from materials, product_recipes, and products
        if (Schema::hasColumn('materials', 'stock')) {
            Schema::table('materials', function (Blueprint $table) {
                $table->dropColumn('stock');
            });
        }

        if (Schema::hasColumn('product_recipes', 'stock')) {
            Schema::table('product_recipes', function (Blueprint $table) {
                $table->dropColumn('stock');
            });
        }

        if (Schema::hasColumn('products', 'stock')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('stock');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('products', 'stock')) {
            Schema::table('products', function (Blueprint $table) {
                $table->integer('stock')->default(0);
            });
        }

        if (! Schema::hasColumn('product_recipes', 'stock')) {
            Schema::table('product_recipes', function (Blueprint $table) {
                $table->integer('stock')->default(0);
            });
        }

        if (! Schema::hasColumn('materials', 'stock')) {
            Schema::table('materials', function (Blueprint $table) {
                $table->integer('stock')->default(0);
            });
        }

        Schema::dropIfExists('product_recipe_stocks');
        Schema::dropIfExists('material_stocks');
    }
};
