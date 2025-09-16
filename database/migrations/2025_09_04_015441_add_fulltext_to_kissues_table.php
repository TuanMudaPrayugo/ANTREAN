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
            // Satu index gabungan untuk dua kolom
            $table->fullText(['issue_name', 'solusion'], 'ft_issue_solution');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kissues', function (Blueprint $table) {
            $table->dropFullText('ft_issue_solution');
        });
    }
};
