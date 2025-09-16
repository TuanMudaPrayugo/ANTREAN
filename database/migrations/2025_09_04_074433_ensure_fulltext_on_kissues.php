<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('kissues', function (Blueprint $table) {
            //
        });
        
        if (! Schema::hasTable('kissues')) return;

        // cek apakah index sudah ada
        $exists = DB::table('information_schema.statistics')
            ->whereRaw('table_schema = DATABASE()')
            ->where('table_name', 'kissues')
            ->where('index_name', 'ft_issue_solution')
            ->exists();

        if (! $exists) {
            // aman untuk MySQL/MariaDB
            DB::statement(
                "ALTER TABLE `kissues`
                 ADD FULLTEXT `ft_issue_solution` (`issue_name`, `solusion`)"
            );
        }

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kissues', function (Blueprint $table) {
            //
        });

         if (! Schema::hasTable('kissues')) return;

        $exists = DB::table('information_schema.statistics')
            ->whereRaw('table_schema = DATABASE()')
            ->where('table_name', 'kissues')
            ->where('index_name', 'ft_issue_solution')
            ->exists();

        if ($exists) {
            DB::statement(
                "ALTER TABLE `kissues`
                 DROP INDEX `ft_issue_solution`"
            );
        }
    }
};
