<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mala_presets', function (Blueprint $table) {
            // null = la borla sigue al material de las cuentas, que es como se
            // veía antes de que el color fuera elegible.
            $table->string('tassel_color', 32)->nullable()->after('material');
        });
    }

    public function down(): void
    {
        Schema::table('mala_presets', function (Blueprint $table) {
            $table->dropColumn('tassel_color');
        });
    }
};
