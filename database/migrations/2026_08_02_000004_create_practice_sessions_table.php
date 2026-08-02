<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sesiones de práctica: eventos append-only e inmutables.
     * El uuid lo genera el CLIENTE — los reintentos de sincronización hacen
     * insert-or-ignore por uuid, de modo que nunca se duplica una sesión.
     * `local_date` viene calculada en el dispositivo con la timezone del
     * usuario al momento de practicar; el servidor no la deriva jamás.
     */
    public function up(): void
    {
        Schema::create('practice_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mantra_id')->constrained()->restrictOnDelete();
            $table->enum('mode', ['traditional', 'assisted']);
            $table->unsignedInteger('recitations');
            $table->unsignedInteger('completed_malas')->default(0);
            $table->dateTime('started_at'); // UTC
            $table->dateTime('ended_at');   // UTC
            $table->unsignedInteger('duration_seconds');
            $table->date('local_date');
            $table->timestamp('synced_at'); // momento en que el servidor la recibió
            $table->timestamps();

            $table->index(['user_id', 'local_date']);
            $table->index(['user_id', 'mantra_id', 'local_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_sessions');
    }
};
