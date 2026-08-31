<?php

namespace Database\Factories;

use App\Enums\MantraColor;
use App\Models\RetreatDeity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RetreatDeity>
 */
class RetreatDeityFactory extends Factory
{
    public function definition(): array
    {
        // Sufijo al azar y no fake()->words(): el slug es único y en un test
        // se crean varias deidades seguidas.
        $suffix = Str::lower(Str::random(8));

        return [
            'slug' => "deidad-{$suffix}",
            'name' => "Deidad {$suffix}",
            'image_path' => null,
            'syllable_image_path' => null,
            'color' => fake()->randomElement(MantraColor::cases())->value,
            'position' => 0,
        ];
    }
}
