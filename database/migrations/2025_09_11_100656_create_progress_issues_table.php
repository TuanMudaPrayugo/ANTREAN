<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Buat pivot hanya kalau belum ada
        if (!Schema::hasTable('progress_issue')) {
            Schema::create('progress_issue', function (Blueprint $table) {
                $table->unsignedBigInteger('progress_id');
                $table->unsignedBigInteger('issue_id');
                $table->timestamps();

                // cegah duplikasi pasangan progress-issue
                $table->primary(['progress_id', 'issue_id']);

                // FK sesuai nama tabel yang benar
                $table->foreign('progress_id')
                      ->references('id')->on('progress')
                      ->cascadeOnDelete();

                $table->foreign('issue_id')
                      ->references('id')->on('k_issues') // <- plural
                      ->cascadeOnDelete();
            });
        }
        // CATATAN: Jangan menambah kolom issue_id ke tabel 'progress' lagi.
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_issue');
    }
};
