<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Compromiso y registro de las Otras recitaciones. Van en sus propias
     * tablas y NO en las de mantras: el objetivo de las recitaciones es
     * independiente y no comparte cuenta con la práctica del mala.
     *
     * - recitation_user: el compromiso diario que fijó cada usuario.
     * - recitation_logs: cuántas veces la recitó por día. local_date la
     *   calcula el dispositivo (§7), igual que en las sesiones.
     */
    public function up(): void
    {
        Schema::create('recitation_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recitation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('daily_commitment')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'recitation_id']);
        });

        Schema::create('recitation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recitation_id')->constrained()->cascadeOnDelete();
            $table->date('local_date');
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'recitation_id', 'local_date']);
            $table->index(['user_id', 'local_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recitation_logs');
        Schema::dropIfExists('recitation_user');
    }
};
