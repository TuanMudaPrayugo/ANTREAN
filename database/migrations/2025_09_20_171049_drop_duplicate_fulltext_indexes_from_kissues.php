<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 🔹 Hapus index yang ganda
        Schema::table('kissues', function (Blueprint $table) {
            DB::statement('ALTER TABLE kissues DROP INDEX kissues_issue_name_solusion_fulltext');
            DB::statement('ALTER TABLE kissues DROP INDEX ft_issue_name');
            DB::statement('ALTER TABLE kissues DROP INDEX ft_issue_solution');
        });
    }

    public function down(): void
    {
        // 🔹 Restore kalau di-rollback
        Schema::table('kissues', function (Blueprint $table) {
            DB::statement('ALTER TABLE kissues ADD FULLTEXT ft_issue_name (issue_name)');
            DB::statement('ALTER TABLE kissues ADD FULLTEXT ft_issue_solution (solusion)');
            DB::statement('ALTER TABLE kissues ADD FULLTEXT kissues_issue_name_solusion_fulltext (issue_name, solusion)');
        });
    }
};
