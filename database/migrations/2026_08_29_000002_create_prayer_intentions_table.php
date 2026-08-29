<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lista de oración: por quién ora cada usuario. A diferencia de los
     * mantras y las recitaciones, acá NO hay contenido del sistema: la lista
     * es privada de cada cuenta y por eso user_id no es nullable.
     *
     * No se cuenta ni se registra por día: es una lista para tener presente,
     * no un checklist. Lo único que cambia con el tiempo es si sigue vigente.
     *
     * archived_at es un timestamp y no un booleano a propósito: guarda CUÁNDO
     * dejó de estar vigente, que es lo que va a alimentar la línea de tiempo
     * de oraciones más adelante. Archivar nunca borra la fila.
     */
    public function up(): void
    {
        Schema::create('prayer_intentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // El texto del motivo "Otro", cuando ninguno del catálogo alcanza.
            $table->string('custom_reason')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            // La lista siempre se pide por usuario y por vigencia.
            $table->index(['user_id', 'archived_at']);
        });

        // Vínculo puro: no guarda preferencias, así que no lleva id ni
        // timestamps. La clave compuesta hace de unique.
        Schema::create('prayer_intention_reason', function (Blueprint $table) {
            $table->foreignId('prayer_intention_id')->constrained()->cascadeOnDelete();
            // restrictOnDelete: un motivo en uso no se borra ni por accidente;
            // el controlador avisa antes de llegar hasta acá.
            $table->foreignId('prayer_reason_id')->constrained()->restrictOnDelete();

            $table->primary(['prayer_intention_id', 'prayer_reason_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prayer_intention_reason');
        Schema::dropIfExists('prayer_intentions');
    }
};
