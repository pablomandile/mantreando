<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El retiro de un usuario sobre una deidad, y su conteo etapa por etapa.
     *
     * Se practica un retiro por vez, pero cambiar de deidad no puede borrar lo
     * andado: hay una fila por (usuario, deidad) y una sola marcada activa.
     *
     * is_active y no "el de activated_at más reciente": la columna timestamp
     * tiene precisión de segundos, así que dos cambios seguidos empatan y el
     * orden queda al azar. El controlador apaga el anterior y prende el nuevo
     * en una transacción.
     *
     * El ábaco NO se guarda: las tres líneas son las últimas tres cifras de
     * count (unidades, decenas, centenas). Guardar la posición de las cuentas
     * sería un segundo estado que puede contradecir al primero.
     */
    public function up(): void
    {
        Schema::create('retreats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('retreat_deity_id')->constrained()->restrictOnDelete();
            $table->date('started_on');
            $table->date('completed_on')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'retreat_deity_id']);
            $table->index(['user_id', 'is_active']);
        });

        Schema::create('retreat_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retreat_id')->constrained()->cascadeOnDelete();
            // restrictOnDelete: borrar una etapa se llevaría puesto el conteo
            // de alguien; el controlador avisa antes de llegar hasta acá.
            $table->foreignId('retreat_mantra_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('count')->default(0);
            // La etapa la cierra el usuario, no la cifra: casi siempre se
            // recita de más antes de pasar a la siguiente.
            $table->date('completed_on')->nullable();
            $table->timestamps();

            $table->unique(['retreat_id', 'retreat_mantra_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retreat_progress');
        Schema::dropIfExists('retreats');
    }
};
