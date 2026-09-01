<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dos campos de texto libre del retiro, sin relación con el conteo:
     *
     * - notes: apuntes de trabajo durante el retiro (qué pasó en cada sesión,
     *   qué instrucción dio el lama, etc.). Privados, se autoguardan.
     * - dedications: el texto de dedicación que se muestra al pie de la
     *   pantalla, plegado a las primeras líneas.
     *
     * longText y no text: el pedido fue explícito ("extenso en db"), y un
     * retiro de 100.000 puede acumular meses de apuntes.
     */
    public function up(): void
    {
        Schema::table('retreats', function (Blueprint $table) {
            $table->longText('notes')->nullable()->after('completed_on');
            $table->longText('dedications')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('retreats', function (Blueprint $table) {
            $table->dropColumn(['notes', 'dedications']);
        });
    }
};
