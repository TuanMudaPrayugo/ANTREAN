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
        Schema::table('kissues', function (Blueprint $table) {
            $table->string('issue_name_norm')->nullable()->index()->after('issue_name');
            $table->json('issue_tokens')->nullable()->after('issue_name_norm');
            $table->json('aliases_norm')->nullable()->after('issue_tokens');
        });

         Schema::table('kissues', function (Blueprint $table) {
            try { $table->fullText(['issue_name', 'solusion']); } catch (\Throwable $e) { /* ignore */ }
        });
    }
            
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('k_issues', function (Blueprint $table) {
            $table->dropColumn(['issue_name_norm','issue_tokens','aliases_norm']);
        });
    }
};
