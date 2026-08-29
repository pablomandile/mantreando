<?php

namespace Database\Factories;

use App\Enums\MantraColor;
use App\Models\PrayerReason;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PrayerReason>
 */
class PrayerReasonFactory extends Factory
{
    public function definition(): array
    {
        // Sufijo al azar y no fake()->words(): el slug es único y estos
        // motivos se crean de a varios en el mismo test.
        $suffix = Str::lower(Str::random(8));

        return [
            'slug' => "motivo-{$suffix}",
            'name' => "Motivo {$suffix}",
            'color' => fake()->randomElement(MantraColor::cases())->value,
            'position' => 0,
        ];
    }
}
