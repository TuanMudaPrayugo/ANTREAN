<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1) Tambah kolom ticket_date bila belum ada
        Schema::table('tickets', function (Blueprint $t) {
            if (!Schema::hasColumn('tickets', 'ticket_date')) {
                $t->date('ticket_date')->nullable()->after('layanan_id');
            }
        });
        DB::statement("UPDATE `tickets` SET `ticket_date` = DATE(`created_at`) WHERE `ticket_date` IS NULL");

        // 2) Pastikan FK 'layanan_id' punya index tunggal sendiri
        Schema::table('tickets', function (Blueprint $t) {
            // kalau sudah ada, try/catch akan diam
            try { $t->index('layanan_id', 'tickets_layanan_id_idx'); } catch (\Throwable $e) {}
        });

        // 3) Drop unique lama (yang dipakai FK sebelumnya), lalu buat unique baru
        //    Nama index bisa berbeda, coba keduanya
        try { DB::statement("ALTER TABLE `tickets` DROP INDEX `tickets_layanan_id_kode_unique`"); } catch (\Throwable $e) {}
        try { DB::statement("ALTER TABLE `tickets` DROP INDEX `tickets_layanan_id_kode_index`"); } catch (\Throwable $e) {}

        // Unique baru: (kode, ticket_date)
        try { DB::statement("ALTER TABLE `tickets` ADD UNIQUE KEY `tickets_kode_date_unique` (`kode`,`ticket_date`)"); } catch (\Throwable $e) {}

        // Index bantu
        try { DB::statement("CREATE INDEX `tickets_layanan_date_idx` ON `tickets`(`layanan_id`,`ticket_date`)"); } catch (\Throwable $e) {}
        try { DB::statement("CREATE INDEX `tickets_layanan_status_idx` ON `tickets`(`layanan_id`,`status`)"); } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        // Balikkan perubahan (opsional)
        try { DB::statement("ALTER TABLE `tickets` DROP INDEX `tickets_kode_date_unique`"); } catch (\Throwable $e) {}
        try { DB::statement("DROP INDEX `tickets_layanan_date_idx` ON `tickets`"); } catch (\Throwable $e) {}
        try { DB::statement("DROP INDEX `tickets_layanan_status_idx` ON `tickets`"); } catch (\Throwable $e) {}

        // Kembalikan unique lama
        try { DB::statement("ALTER TABLE `tickets` ADD UNIQUE KEY `tickets_layanan_id_kode_unique` (`layanan_id`,`kode`)"); } catch (\Throwable $e) {}

        // Hapus kolom ticket_date bila mau benar-benar rollback
        if (Schema::hasColumn('tickets','ticket_date')) {
            Schema::table('tickets', function (Blueprint $t) { $t->dropColumn('ticket_date'); });
        }
    }
};
