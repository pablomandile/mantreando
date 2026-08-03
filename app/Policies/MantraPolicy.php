<?php

namespace App\Policies;

use App\Models\Mantra;
use App\Models\User;

class MantraPolicy
{
    /** Ver el detalle: mantras del sistema o propios. */
    public function view(User $user, Mantra $mantra): bool
    {
        return $mantra->isSystem() || $mantra->user_id === $user->id;
    }

    /** Editar: solo mantras propios (los del sistema son de solo lectura). */
    public function update(User $user, Mantra $mantra): bool
    {
        return $mantra->user_id === $user->id;
    }

    public function delete(User $user, Mantra $mantra): bool
    {
        return $mantra->user_id === $user->id;
    }
}
