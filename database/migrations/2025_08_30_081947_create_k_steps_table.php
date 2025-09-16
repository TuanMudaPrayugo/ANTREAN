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
        Schema::create('k_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('layanan_id')
                ->constrained('k_layanans')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->string('service_step_name');
            $table->integer('step_order');
            $table->integer('std_step_time');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('k_steps');
    }
};
