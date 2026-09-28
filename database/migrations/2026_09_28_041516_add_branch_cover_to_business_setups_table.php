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
        Schema::table('business_setups', function (Blueprint $table) {
            if (! Schema::hasColumn('business_setups', 'branch_cover')) {
                $table->decimal('branch_cover', 8, 2)->default(5.00)->after('description');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_setups', function (Blueprint $table) {
            if (Schema::hasColumn('business_setups', 'branch_cover')) {
                $table->dropColumn('branch_cover');
            }
        });
    }
};
