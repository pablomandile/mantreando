<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Presets de personalización del mala. Hoy cada usuario usa uno activo;
     * la tabla admite varios para las "colecciones de malas" futuras.
     * Las texturas subidas son privadas por usuario (decisión del plan).
     */
    public function up(): void
    {
        Schema::create('mala_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('Mi mala');
            $table->string('material', 32)->default('wood'); // wood|bodhi|red|blue
            $table->string('texture_path')->nullable(); // imagen propia opcional
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mala_presets');
    }
};
