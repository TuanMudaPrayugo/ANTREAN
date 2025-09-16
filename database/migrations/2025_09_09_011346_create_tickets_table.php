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
        // Tambah kolom ticket_date kalau belum ada
        Schema::table('tickets', function (Blueprint $t) {
            if (!Schema::hasColumn('tickets', 'ticket_date')) {
                $t->date('ticket_date')->nullable()->after('layanan_id')->index();
            }
        });

        // Backfill dari created_at (hanya yang null)
        DB::table('tickets')
            ->whereNull('ticket_date')
            ->update(['ticket_date' => DB::raw('DATE(created_at)')]);

        // Drop unique lama (nama index bisa beda, jadi coba dua cara)
        Schema::table('tickets', function (Blueprint $t) {
            try { $t->dropUnique('tickets_layanan_id_kode_unique'); } catch (\Throwable $e) {}
            try { $t->dropUnique(['layanan_id', 'kode']); } catch (\Throwable $e) {}
        });

        // Unique baru: (kode, ticket_date)
        Schema::table('tickets', function (Blueprint $t) {
            try { $t->unique(['kode','ticket_date'], 'tickets_kode_date_unique'); } catch (\Throwable $e) {}
            $t->index(['layanan_id','ticket_date'], 'tickets_layanan_date_idx');
            $t->index(['layanan_id','status'], 'tickets_layanan_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $t) {
            try { $t->dropUnique('tickets_kode_date_unique'); } catch (\Throwable $e) {}
            try { $t->dropIndex('tickets_layanan_date_idx'); } catch (\Throwable $e) {}
            try { $t->dropIndex('tickets_layanan_status_idx'); } catch (\Throwable $e) {}
            if (Schema::hasColumn('tickets','ticket_date')) {
                $t->dropColumn('ticket_date');
            }
        });

        Schema::table('tickets', function (Blueprint $t) {
            // Pulihkan unique lama
            try { $t->unique(['layanan_id','kode'], 'tickets_layanan_id_kode_unique'); } catch (\Throwable $e) {}
        });
    }
};