<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progress', function (Blueprint $table) {
            // pakai nama tabel yang benar: 'progress'
            if (!Schema::hasColumn('progress','issue_id')) {
                // kalau tabel issue-mu bernama 'k_issues', biarkan seperti ini
                $table->unsignedBigInteger('issue_id')->nullable()->after('status');
                $table->foreign('issue_id')->references('id')->on('k_issues')->nullOnDelete();
            }

            if (!Schema::hasColumn('progress','note')) {
                $table->text('note')->nullable()->after('issue_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('progress', function (Blueprint $table) {
            if (Schema::hasColumn('progress','issue_id')) {
                $table->dropForeign(['issue_id']);
                $table->dropColumn('issue_id');
            }
            if (Schema::hasColumn('progress','note')) {
                $table->dropColumn('note');
            }
        });
    }
};
