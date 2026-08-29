<?php

namespace Database\Factories;

use App\Models\PrayerIntention;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrayerIntention>
 */
class PrayerIntentionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->name(),
            'custom_reason' => null,
            'archived_at' => null,
        ];
    }

    /** Persona de la lista de un usuario concreto. */
    public function ownedBy(User $user): static
    {
        return $this->state(fn (array $attributes) => ['user_id' => $user->id]);
    }

    /** Ya no está en la lista activa, pero su fila se conserva. */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => ['archived_at' => now()]);
    }
}
