<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', [
                'preparing',
                'done',
                'pickup',
                'out_of_delivery',
                'delivered',
                'return',
                'failed_to_delivered',
                'faild_to_delivered',
            ])->default('preparing')->after('is_pos');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
