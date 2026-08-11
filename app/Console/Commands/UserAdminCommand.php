<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Da o quita el rol de administrador. Es la única vía: la columna no es
 * fillable, así que no hay forma de llegar a ella desde la app (ni un formulario
 * manipulado, ni una respuesta de Google).
 */
class UserAdminCommand extends Command
{
    protected $signature = 'user:admin
                            {email : Mail de la cuenta}
                            {--revoke : Quitar el rol en lugar de darlo}';

    protected $description = 'Da (o quita con --revoke) el rol de administrador a un usuario';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->components->error("No hay ninguna cuenta con el mail {$email}.");

            return self::FAILURE;
        }

        $grant = ! $this->option('revoke');

        if ($user->is_admin === $grant) {
            $this->components->info($grant
                ? "{$email} ya era administrador."
                : "{$email} no era administrador.");

            return self::SUCCESS;
        }

        $user->is_admin = $grant;
        $user->save();

        $this->components->info($grant
            ? "{$email} ahora es administrador."
            : "Rol de administrador quitado a {$email}.");

        return self::SUCCESS;
    }
}
