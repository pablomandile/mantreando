<?php

namespace App\Policies;

use App\Models\User;

/**
 * Las etapas cuelgan de una deidad del sistema: mismo criterio que
 * RetreatDeityPolicy, solo un administrador las carga o las corrige.
 */
class RetreatMantraPolicy
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
