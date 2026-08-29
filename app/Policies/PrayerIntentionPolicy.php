<?php

namespace App\Policies;

use App\Models\PrayerIntention;
use App\Models\User;

/**
 * La lista de oración es privada: solo su dueño la ve y la toca. Ni siquiera
 * un administrador, que acá no tiene nada que mantener.
 */
class PrayerIntentionPolicy
{
    public function view(User $user, PrayerIntention $intention): bool
    {
        return $intention->user_id === $user->id;
    }

    public function update(User $user, PrayerIntention $intention): bool
    {
        return $this->view($user, $intention);
    }

    public function delete(User $user, PrayerIntention $intention): bool
    {
        return $this->view($user, $intention);
    }
}
