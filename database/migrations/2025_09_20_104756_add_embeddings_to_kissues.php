<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::table('kissues', function (Blueprint $t) {
            $t->binary('emb')->nullable();           // float32[] packed (little endian)
            $t->smallInteger('emb_dim')->nullable();
            $t->string('emb_model', 64)->nullable();
            $t->dateTime('last_indexed_at')->nullable();
        });

        // Fulltext index untuk BM25 (InnoDB)
        try {
            DB::statement("ALTER TABLE kissues ADD FULLTEXT ft_issue_sol (issue_name, solusion)");
        } catch (\Throwable $e) {
            // abaikan jika sudah ada
        }
    }

    public function down(): void {
        try {
            DB::statement("ALTER TABLE kissues DROP INDEX ft_issue_sol");
        } catch (\Throwable $e) {}
        Schema::table('kissues', function (Blueprint $t) {
            $t->dropColumn(['emb','emb_dim','emb_model','last_indexed_at']);
        });
    }
};
