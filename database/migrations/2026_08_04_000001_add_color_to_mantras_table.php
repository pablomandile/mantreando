<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Color de la tarjeta (ver App\Enums\MantraColor). Se guarda solo la
     * elección; el degradado lo arma CSS. Nullable: un mantra sin color
     * usa el fondo neutro de siempre.
     */
    public function up(): void
    {
        Schema::table('mantras', function (Blueprint $table) {
            $table->string('color', 20)->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('mantras', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
