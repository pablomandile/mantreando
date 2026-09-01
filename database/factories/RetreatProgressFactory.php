<?php

namespace Database\Factories;

use App\Models\Retreat;
use App\Models\RetreatMantra;
use App\Models\RetreatProgress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RetreatProgress>
 */
class RetreatProgressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'retreat_id' => Retreat::factory(),
            'retreat_mantra_id' => RetreatMantra::factory(),
            'count' => 0,
            'completed_on' => null,
        ];
    }
}
