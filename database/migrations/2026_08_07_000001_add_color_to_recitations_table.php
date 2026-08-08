<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Color de la tarjeta (App\Enums\MantraColor, la paleta compartida de la
     * app). Se guarda solo la elección; el degradado lo arma CSS, igual que
     * en los mantras. Las recitaciones son todas del sistema: lo pone el
     * seeder, no el usuario.
     */
    public function up(): void
    {
        Schema::table('recitations', function (Blueprint $table) {
            $table->string('color', 20)->nullable()->after('text');
        });
    }

    public function down(): void
    {
        Schema::table('recitations', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
