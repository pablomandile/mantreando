<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Preferencias por usuario sobre mantras (propios o del sistema).
     * Los mantras del sistema son compartidos, por eso favoritos y
     * compromisos viven en esta pivot y no en `mantras`.
     */
    public function up(): void
    {
        Schema::create('mantra_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mantra_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_favorite')->default(false);
            $table->unsignedInteger('daily_commitment')->nullable(); // recitaciones/día
            $table->unsignedBigInteger('total_goal')->nullable();    // objetivo acumulado
            $table->timestamps();

            $table->unique(['user_id', 'mantra_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mantra_user');
    }
};
