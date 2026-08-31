<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retiro de aproximación: el catálogo de deidades y los mantras que se
     * recitan para cada una, en orden.
     *
     * Los mantras del retiro NO son los de la biblioteca: son textos propios
     * del retiro, con su cantidad, que carga un administrador. Un retiro de
     * Vajrasattva son las cien sílabas, después el mantra corto y después la
     * sílaba semilla, cada uno con su cifra (100.000, 100.000, 10.000): la
     * cantidad nunca se asume, se carga.
     *
     * El slug es la identidad estable: permite corregir el nombre sin que el
     * seeder duplique la fila.
     */
    public function up(): void
    {
        Schema::create('retreat_deities', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name');
            // Dos imágenes: la deidad y su sílaba. Misma convención que en
            // mantras: si empieza con 'img/' vive en public/, si no, en el
            // disco public (storage/app/public).
            $table->string('image_path')->nullable();
            $table->string('syllable_image_path')->nullable();
            // App\Enums\MantraColor: tiñe las cuentas del ábaco.
            $table->string('color', 20)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index('position');
        });

        Schema::create('retreat_mantras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retreat_deity_id')->constrained()->cascadeOnDelete();
            // El orden de recitación: terminada la cifra de uno, sigue el otro.
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('name');
            $table->text('text');
            $table->unsignedInteger('goal');
            $table->timestamps();

            $table->index(['retreat_deity_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retreat_mantras');
        Schema::dropIfExists('retreat_deities');
    }
};
