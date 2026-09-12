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
        Schema::table('hall_tables', function (Blueprint $table) {
            if (! Schema::hasColumn('hall_tables', 'qr')) {
                $table->string('qr')->nullable()->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hall_tables', function (Blueprint $table) {
            if (Schema::hasColumn('hall_tables', 'qr')) {
                $table->dropColumn('qr');
            }
        });
    }
};
