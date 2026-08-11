<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rol de administrador: puede publicar mantras para todos (user_id null) y
     * mantener las "otras recitaciones", que son globales por naturaleza.
     *
     * Un booleano y no una tabla de roles: hay un solo rol y no hay permisos
     * que combinar. La columna NO es fillable en el modelo, así que no se puede
     * llegar a ella por mass assignment desde el formulario de perfil; se
     * asigna con `php artisan user:admin`.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('theme');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
