<?php

namespace Database\Factories;

use App\Models\MantraCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MantraCategory>
 */
class MantraCategoryFactory extends Factory
{
    public function definition(): array
    {
        $es = fake()->unique()->words(2, true);

        return [
            'name' => ['es' => Str::ucfirst($es), 'en' => Str::ucfirst(fake()->words(2, true))],
            'slug' => Str::slug($es),
            'position' => fake()->numberBetween(0, 10),
        ];
    }
}
