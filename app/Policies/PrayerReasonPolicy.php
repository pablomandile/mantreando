<?php

namespace App\Policies;

use App\Models\User;

/**
 * El catálogo de motivos es global (ver la migración): sumar o corregir uno
 * cambia lo que ven todas las cuentas, así que es cosa de un administrador.
 * Elegir motivos al cargar una persona lo hace cualquiera, y por eso no hay
 * un `view` acá.
 */
class PrayerReasonPolicy
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
