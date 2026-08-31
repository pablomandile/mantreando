<?php

namespace Database\Factories;

use App\Models\RetreatDeity;
use App\Models\RetreatMantra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RetreatMantra>
 */
class RetreatMantraFactory extends Factory
{
    public function definition(): array
    {
        return [
            'retreat_deity_id' => RetreatDeity::factory(),
            'position' => 1,
            'name' => 'Mantra del retiro',
            'text' => 'OM AH HUM',
            'goal' => 100000,
        ];
    }
}
