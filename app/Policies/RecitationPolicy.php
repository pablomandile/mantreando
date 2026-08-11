<?php

namespace App\Policies;

use App\Models\User;

/**
 * Las recitaciones son globales por naturaleza (no tienen dueño: ver la
 * migración), así que mantenerlas es cosa de un administrador. Leerlas puede
 * cualquiera, y por eso no hay un `view` acá.
 */
class RecitationPolicy
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
