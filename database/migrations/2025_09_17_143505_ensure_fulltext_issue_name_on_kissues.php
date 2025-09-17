<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Cek apakah FULLTEXT utk issue_name sudah ada
        $exists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', 'kissues')
            ->where('index_name', 'ft_issue_name')
            ->exists();

        if (!$exists) {
            // Coba pakai Blueprint::fullText (Laravel 9+)
            try {
                Schema::table('kissues', function (Blueprint $table) {
                    if (method_exists($table, 'fullText')) {
                        $table->fullText('issue_name', 'ft_issue_name');
                    }
                });
            } catch (\Throwable $e) {
                // Fallback raw SQL (kalau driver lama/tidak support)
                try {
                    DB::statement('ALTER TABLE `kissues` ADD FULLTEXT `ft_issue_name` (`issue_name`)');
                } catch (\Throwable $e2) {
                    // sudah ada / tidak didukung → diamkan
                }
            }
        }
    }

    public function down(): void
    {
        // Hapus index ini saja kalau kita yang menambahkannya
        try {
            Schema::table('kissues', function (Blueprint $table) {
                $table->dropIndex('ft_issue_name'); // FULLTEXT juga pakai dropIndex
            });
        } catch (\Throwable $e) {
            // abaikan jika tidak ada
        }
    }
};
