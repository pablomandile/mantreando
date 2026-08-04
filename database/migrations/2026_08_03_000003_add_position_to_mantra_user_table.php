<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Orden personal de la biblioteca: cada usuario acomoda SUS tarjetas
     * (los mantras del sistema son compartidos, por eso el orden vive en
     * la pivot). NULL = sin ordenar (va al final, alfabético).
     */
    public function up(): void
    {
        Schema::table('mantra_user', function (Blueprint $table) {
            $table->unsignedInteger('position')->nullable()->after('total_goal');
        });
    }

    public function down(): void
    {
        Schema::table('mantra_user', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
