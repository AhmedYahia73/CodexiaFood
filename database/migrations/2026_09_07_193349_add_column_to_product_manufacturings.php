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
        Schema::table('product_recipe_manufacturings', function (Blueprint $table) {
            $table->foreignId('product_manufact_id')->nullable()->constrained('product_manufacturings')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_manufacturings', function (Blueprint $table) {
            //
        });
    }
};
