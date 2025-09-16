<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('origin_fp', 128)->nullable()->after('join_pin');
            $table->string('origin_ip', 64)->nullable()->after('origin_fp');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['origin_fp', 'origin_ip']);
        });
    }
};
