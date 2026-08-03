<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Traducciones del contenido de mantras del SISTEMA:
     * {"en": {"name": ..., "translation": ..., "description": ..., "benefits": ...}}
     * Las columnas base quedan en español (idioma fuente). Los mantras de
     * usuario no se traducen (translations = null → siempre columnas base).
     */
    public function up(): void
    {
        Schema::table('mantras', function (Blueprint $table) {
            $table->json('translations')->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('mantras', function (Blueprint $table) {
            $table->dropColumn('translations');
        });
    }
};
