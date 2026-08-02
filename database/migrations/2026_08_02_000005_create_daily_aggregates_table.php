<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agregados diarios precalculados: el dashboard lee de acá, nunca suma
     * sesiones crudas en rangos largos. Una fila por (usuario, mantra, día)
     * más una fila "total del día" con mantra_id = null.
     *
     * `mantra_key` es una columna generada COALESCE(mantra_id, 0): MySQL trata
     * los NULL como distintos en índices únicos, así que el unique real se
     * define sobre la columna generada.
     */
    public function up(): void
    {
        Schema::create('daily_aggregates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // restrict (no SET NULL): InnoDB prohíbe acciones en cascada sobre la
            // columna base de una generada STORED. Un mantra con historial no se
            // borra duro — practice_sessions ya lo restringe igual.
            $table->foreignId('mantra_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('mantra_key')->storedAs('coalesce(mantra_id, 0)');
            $table->date('local_date');
            $table->unsignedInteger('recitations')->default(0);
            $table->unsignedInteger('malas')->default(0);
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedInteger('sessions_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'mantra_key', 'local_date']);
            $table->index(['user_id', 'local_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_aggregates');
    }
};
