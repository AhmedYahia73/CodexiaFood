<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            $table->foreignId('kitchen_id')->nullable()->after('discount_id')->constrained('kitchens')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kitchen_id');
        });
    }
};
