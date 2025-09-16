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
        Schema::table('tickets', function (Blueprint $table) {
        if (!Schema::hasColumn('tickets', 'join_pin')) {
            $table->string('join_pin', 10)->nullable()->after('status');
        }
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       Schema::table('tickets', function (Blueprint $table) {
        if (Schema::hasColumn('tickets', 'join_pin')) {
            $table->dropColumn('join_pin');
        }
    });
}
};
