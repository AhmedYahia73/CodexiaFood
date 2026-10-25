<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'code')) {
                $table->uuid('code')->nullable()->unique()->after('id');
            }
        });

        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'code')) {
            DB::table('orders')->whereNull('code')->get()->each(function ($order) {
                DB::table('orders')->where('id', $order->id)->update([
                    'code' => (string) Str::uuid(),
                ]);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'code')) {
                $table->dropColumn('code');
            }
        });
    }
};
