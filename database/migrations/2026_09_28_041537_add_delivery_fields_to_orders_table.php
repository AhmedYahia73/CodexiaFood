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
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('hall_table_id')->constrained('branches')->nullOnDelete();
            }
            if (! Schema::hasColumn('orders', 'lat')) {
                $table->decimal('lat', 10, 7)->nullable()->after('address');
            }
            if (! Schema::hasColumn('orders', 'lng')) {
                $table->decimal('lng', 10, 7)->nullable()->after('lat');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'branch_id')) {
                $table->dropConstrainedForeignId('branch_id');
            }
            if (Schema::hasColumn('orders', 'lat')) {
                $table->dropColumn('lat');
            }
            if (Schema::hasColumn('orders', 'lng')) {
                $table->dropColumn('lng');
            }
        });
    }
};
