<?php

namespace Database\Factories;

use App\Models\Retreat;
use App\Models\RetreatDeity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Retreat>
 */
class RetreatFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'retreat_deity_id' => RetreatDeity::factory(),
            'started_on' => now()->toDateString(),
            'completed_on' => null,
            'is_active' => true,
        ];
    }

    /** Retiro de un usuario concreto. */
    public function ownedBy(User $user): static
    {
        return $this->state(fn (array $attributes) => ['user_id' => $user->id]);
    }
}
