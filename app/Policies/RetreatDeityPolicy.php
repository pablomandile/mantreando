<?php

namespace App\Policies;

use App\Models\User;

/**
 * Las deidades del retiro y sus mantras son contenido global: lo que se carga
 * acá aparece en la app de todas las cuentas, así que lo mantiene un
 * administrador. Elegir una deidad y contar lo hace cualquiera, y por eso no
 * hay un `view`.
 */
class RetreatDeityPolicy
{
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user): bool
    {
        return $user->isAdmin();
    }
}
