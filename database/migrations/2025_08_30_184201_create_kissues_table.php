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
        Schema::create('kissues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('layanan_id')
                ->constrained('k_layanans')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->foreignId('steplayanan_id')
                ->constrained('k_steps')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->foreignId('categoryissue_id')
                ->constrained('kategori_issues')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->fullText(['issue_name', 'solusion'], 'ft_kissues');
            $table->string('issue_name');
            $table->text('solusion')->nullable();
            $table->integer('std_solution_time');
            $table->timestamps();

            
        });

        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Schema::table('kissues', function (Blueprint $table) {
            $table->dropFullText('ft_kissues'); // atau: $table->dropIndex('ft_kissues');
        });
        
        
    }
};
