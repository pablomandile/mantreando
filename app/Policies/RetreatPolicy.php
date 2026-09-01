<?php

namespace App\Policies;

use App\Models\Retreat;
use App\Models\User;

/**
 * El conteo de un retiro es privado: solo su dueño lo ve y lo mueve. Ni
 * siquiera un administrador, que acá no tiene nada que mantener.
 */
class RetreatPolicy
{
    public function view(User $user, Retreat $retreat): bool
    {
        return $retreat->user_id === $user->id;
    }

    public function update(User $user, Retreat $retreat): bool
    {
        return $this->view($user, $retreat);
    }

    public function delete(User $user, Retreat $retreat): bool
    {
        return $this->view($user, $retreat);
    }
}
