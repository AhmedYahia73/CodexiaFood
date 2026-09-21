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
        Schema::table('hall_tables', function (Blueprint $table) {
            if (! Schema::hasColumn('hall_tables', 'code')) {
                $table->uuid('code')->nullable()->unique()->after('id');
            }
        });

        if (Schema::hasTable('hall_tables') && Schema::hasColumn('hall_tables', 'code')) {
            DB::table('hall_tables')->whereNull('code')->get()->each(function ($table) {
                DB::table('hall_tables')->where('id', $table->id)->update([
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
        Schema::table('hall_tables', function (Blueprint $table) {
            if (Schema::hasColumn('hall_tables', 'code')) {
                $table->dropColumn('code');
            }
        });
    }
};
