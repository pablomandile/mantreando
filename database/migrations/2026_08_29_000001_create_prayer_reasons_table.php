<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Motivos de la Lista de oración: por qué se ora por alguien (paz mental,
     * recuperación, renacimiento afortunado…).
     *
     * Es un catálogo global y no un enum porque la idea es que un
     * administrador pueda sumar motivos nuevos sin tocar código, igual que
     * mantiene las Otras recitaciones. El motivo escrito a mano NO vive acá:
     * es de una sola persona y va en prayer_intentions.custom_reason.
     *
     * El slug es la identidad estable: permite corregir el nombre sin que el
     * seeder duplique la fila.
     */
    public function up(): void
    {
        Schema::create('prayer_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name');
            // App\Enums\MantraColor: la paleta compartida de las tarjetas.
            $table->string('color', 20)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index('position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prayer_reasons');
    }
};
