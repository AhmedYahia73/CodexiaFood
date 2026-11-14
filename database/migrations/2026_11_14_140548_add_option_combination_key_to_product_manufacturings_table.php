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
        Schema::table('product_manufacturings', function (Blueprint $table) {
            // Sorted comma-separated option IDs (e.g. "5,12").
            // NULL = product-level recipe (no option combination).
            $table->string('option_combination_key')->nullable()->after('product_id');

            // Drop the old single-row constraint and allow one row per combination.
            $table->unique(['product_id', 'option_combination_key'], 'pm_product_option_combo_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_manufacturings', function (Blueprint $table) {
            $table->dropUnique('pm_product_option_combo_unique');
            $table->dropColumn('option_combination_key');
        });
    }
};
