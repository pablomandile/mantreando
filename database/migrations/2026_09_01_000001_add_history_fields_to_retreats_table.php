<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tres campos para el historial de retiros:
     *
     * - first_counted_on / last_counted_on: la fecha real de la primera y la
     *   última cuenta pasada. started_on se pone al ACTIVAR la deidad, que
     *   puede ser días antes de tocar una sola cuenta; estos dos reflejan la
     *   práctica en sí, no la elección del selector.
     * - archived_on: cuándo el usuario guardó el retiro terminado en su
     *   historial. No es lo mismo que completed_on (que se pone solo al
     *   cerrar la última etapa): terminar el conteo no manda nada a la
     *   pizarra sin que el usuario lo confirme a mano con "Guardar datos" —
     *   hasta entonces el retiro sigue siendo el activo, mostrando la
     *   pantalla de felicitaciones cada vez que vuelve.
     */
    public function up(): void
    {
        Schema::table('retreats', function (Blueprint $table) {
            $table->date('first_counted_on')->nullable()->after('completed_on');
            $table->date('last_counted_on')->nullable()->after('first_counted_on');
            $table->date('archived_on')->nullable()->after('last_counted_on');
        });
    }

    public function down(): void
    {
        Schema::table('retreats', function (Blueprint $table) {
            $table->dropColumn(['first_counted_on', 'last_counted_on', 'archived_on']);
        });
    }
};
