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
            // FULLTEXT hanya di kolom issue_name
            $table->fullText('issue_name', 'ft_issue_name');

            // FULLTEXT di issue_name + solusion
            $table->fullText(['issue_name', 'solusion'], 'ft_title_body');

            // Index biasa untuk exact match issue_name_norm
            $table->index('issue_name_norm', 'idx_issue_name_norm');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kissues', function (Blueprint $table) {
            $table->dropFullText('ft_issue_name');
            $table->dropFullText('ft_title_body');
            $table->dropIndex('idx_issue_name_norm');
        });
    }
};
