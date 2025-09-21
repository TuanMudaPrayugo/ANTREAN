<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::table('kissues', function (Blueprint $table) {
            if (!Schema::hasColumn('kissues', 'aliases_norm')) {
                $table->json('aliases_norm')->nullable()->after('issue_tokens');
            }
        });
        // normalisasi null => []
        DB::table('kissues')->whereNull('aliases_norm')->update(['aliases_norm' => DB::raw("JSON_ARRAY()")]);
    }
    public function down(): void {
        // tidak perlu rollback kolom ini, aman dibiarkan
    }
};
