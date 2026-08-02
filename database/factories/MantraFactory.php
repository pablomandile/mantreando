<?php

namespace Database\Factories;

use App\Models\Mantra;
use App\Models\MantraCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mantra>
 */
class MantraFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => null, // por defecto, mantra del sistema
            'category_id' => MantraCategory::factory(),
            'name' => fake()->words(3, true),
            'original_name' => null,
            'transliteration' => fake()->optional()->sentence(4),
            'text' => fake()->sentence(6),
            'translation' => fake()->optional()->sentence(8),
            'description' => fake()->optional()->paragraph(),
            'benefits' => fake()->optional()->paragraph(),
            'image_path' => null,
        ];
    }

    /** Mantra propio de un usuario. */
    public function ownedBy(User $user): static
    {
        return $this->state(fn (array $attributes) => ['user_id' => $user->id]);
    }
}
