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
            if (! Schema::hasColumn('purchases', 'paid_amount')) {
                $table->decimal('paid_amount', 12, 2)->default(0.00)->after('total_cost');
            }
            if (! Schema::hasColumn('purchases', 'remaining_amount')) {
                $table->decimal('remaining_amount', 12, 2)->default(0.00)->after('paid_amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('purchases', 'paid_amount')) {
                $cols[] = 'paid_amount';
            }
            if (Schema::hasColumn('purchases', 'remaining_amount')) {
                $cols[] = 'remaining_amount';
            }
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
