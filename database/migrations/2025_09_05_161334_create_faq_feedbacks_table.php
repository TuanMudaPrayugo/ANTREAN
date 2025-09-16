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
        Schema::create('faq_feedbacks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('issue_id')->nullable()->index(); // KIssue.id yang dijawab
            $table->string('session_id', 64)->nullable()->index();       // id sesi/visitor (bebas)
            $table->text('user_query');                                  // pertanyaan asli user
            $table->boolean('is_helpful')->default(false);               // ya/tidak
            $table->json('alternatives')->nullable();                    // judul2 yang ditawarkan
            $table->timestamps();

            // kalau mau, tambahkan FK:
            // $table->foreign('issue_id')->references('id')->on('k_issues')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faq_feedbacks');
    }
};
