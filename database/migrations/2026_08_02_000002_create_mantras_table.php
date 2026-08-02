<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mantras', function (Blueprint $table) {
            $table->id();
            // null = mantra del sistema (compartido entre todos los usuarios)
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('mantra_categories')->restrictOnDelete();
            $table->string('name');
            $table->string('original_name')->nullable(); // p. ej. en tibetano/sánscrito
            $table->string('transliteration')->nullable();
            $table->text('text');
            $table->text('translation')->nullable();
            $table->text('description')->nullable();
            $table->text('benefits')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mantras');
    }
};
