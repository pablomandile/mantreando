<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rachas por usuario: la fila con mantra_id = null es la racha global
     * (obligatoria); las filas por mantra son métrica secundaria.
     * La lógica de actualización se implementa en la etapa de estadísticas;
     * el esquema se congela ahora para estabilidad del modelo.
     */
    public function up(): void
    {
        Schema::create('streaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // restrict: mismas reglas de InnoDB que en daily_aggregates (mantra_key STORED).
            $table->foreignId('mantra_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('mantra_key')->storedAs('coalesce(mantra_id, 0)');
            $table->unsignedInteger('current_count')->default(0);
            $table->unsignedInteger('max_count')->default(0);
            $table->date('last_local_date')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'mantra_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('streaks');
    }
};
