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
        Schema::create('progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')
                  ->constrained('tickets')
                  ->cascadeOnDelete();
            $table->foreignId('step_id')
                  ->constrained('k_steps')
                  ->cascadeOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->enum('status', ['pending', 'running', 'done', 'stopped'])
                  ->default('pending')
                  ->index();
            $table->unique(['ticket_id', 'step_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('progress');
    }
};
