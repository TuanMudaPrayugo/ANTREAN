<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('progress', function (Blueprint $t) {
            if (!Schema::hasColumn('progress','issue_id')) {
                $t->unsignedBigInteger('issue_id')->nullable()->after('step_id')->index();
            }
            if (!Schema::hasColumn('progress','issue_note')) {
                $t->text('issue_note')->nullable()->after('issue_id');
            }
        });
    }
    public function down(): void {
        Schema::table('progress', function (Blueprint $t) {
            if (Schema::hasColumn('progress','issue_note')) $t->dropColumn('issue_note');
            if (Schema::hasColumn('progress','issue_id'))   $t->dropColumn('issue_id');
        });
    }
};
