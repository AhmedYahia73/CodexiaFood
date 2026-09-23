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
        Schema::table('manufacturing_lists', function (Blueprint $table) {
            if (! Schema::hasColumn('manufacturing_lists', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('id')->constrained('branches')->cascadeOnUpdate()->nullOnDelete();
            }
        });

        Schema::table('purchases', function (Blueprint $table) {
            if (! Schema::hasColumn('purchases', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('id')->constrained('branches')->cascadeOnUpdate()->nullOnDelete();
            }
        });

        Schema::table('wastes', function (Blueprint $table) {
            if (! Schema::hasColumn('wastes', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('id')->constrained('branches')->cascadeOnUpdate()->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wastes', function (Blueprint $table) {
            if (Schema::hasColumn('wastes', 'branch_id')) {
                $table->dropConstrainedForeignId('branch_id');
            }
        });

        Schema::table('purchases', function (Blueprint $table) {
            if (Schema::hasColumn('purchases', 'branch_id')) {
                $table->dropConstrainedForeignId('branch_id');
            }
        });

        Schema::table('manufacturing_lists', function (Blueprint $table) {
            if (Schema::hasColumn('manufacturing_lists', 'branch_id')) {
                $table->dropConstrainedForeignId('branch_id');
            }
        });
    }
};
