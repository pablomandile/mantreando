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
        // words() declara array|string según asString: se normaliza a string.
        $esWords = fake()->unique()->words(2);
        $enWords = fake()->words(2);
        $es = is_array($esWords) ? implode(' ', $esWords) : $esWords;
        $en = is_array($enWords) ? implode(' ', $enWords) : $enWords;

        return [
            'name' => ['es' => Str::ucfirst($es), 'en' => Str::ucfirst($en)],
            'slug' => Str::slug($es),
            'position' => fake()->numberBetween(0, 10),
        ];
    }
}
