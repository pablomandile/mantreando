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

    /**
     * Editar: los propios siempre; los del sistema solo un administrador
     * (para el resto siguen siendo de solo lectura).
     */
    public function update(User $user, Mantra $mantra): bool
    {
        return $mantra->user_id === $user->id
            || ($mantra->isSystem() && $user->isAdmin());
    }

    public function delete(User $user, Mantra $mantra): bool
    {
        return $this->update($user, $mantra);
    }

    /**
     * Publicar para todos (o dejar de hacerlo). Separado de update porque un
     * usuario común edita sus mantras pero no decide la visibilidad de nadie.
     */
    public function share(User $user): bool
    {
        return $user->isAdmin();
    }
}
