<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Otras recitaciones: oraciones y yogas que se leen, no se cuentan en el
     * mala. Por eso son una tabla aparte y no un tipo de mantra: no tienen
     * conteo, ni sesiones, ni objetivo, y su texto es largo.
     *
     * El slug es la identidad estable: permite corregir el título sin que el
     * seeder duplique la fila.
     */
    public function up(): void
    {
        Schema::create('recitations', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('title');
            $table->text('text');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index('position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recitations');
    }
};
