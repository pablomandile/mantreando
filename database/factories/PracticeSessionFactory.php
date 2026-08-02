<?php

namespace Database\Factories;

use App\Enums\PracticeMode;
use App\Models\Mantra;
use App\Models\PracticeSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PracticeSession>
 */
class PracticeSessionFactory extends Factory
{
    public function definition(): array
    {
        $durationSeconds = fake()->numberBetween(60, 3600);
        $startedAt = fake()->dateTimeBetween('-30 days', '-1 hour');
        $endedAt = (clone $startedAt)->modify("+{$durationSeconds} seconds");
        $recitations = fake()->numberBetween(1, 324);

        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'mantra_id' => Mantra::factory(),
            'mode' => fake()->randomElement(PracticeMode::cases()),
            'recitations' => $recitations,
            'completed_malas' => intdiv($recitations, 108),
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'duration_seconds' => $durationSeconds,
            // En producción la calcula el dispositivo; acá derivamos una coherente.
            'local_date' => $startedAt->format('Y-m-d'),
            'synced_at' => now(),
        ];
    }
}
