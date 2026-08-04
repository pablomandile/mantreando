<?php

namespace App\Actions\Practice;

use App\Models\Streak;
use App\Models\User;
use Illuminate\Support\Carbon;

class UpdateStreaks
{
    /**
     * Recalcula rachas desde daily_aggregates (nunca desde sesiones crudas).
     *
     * Se RECALCULA en vez de incrementar: las sesiones offline pueden llegar
     * fuera de orden (una práctica de ayer sincronizada hoy) y un contador
     * incremental se rompería. Los local_date históricos jamás se recalculan
     * (§7): un cambio de timezone por viaje no reescribe rachas pasadas.
     *
     * @param  array<int, int>  $mantraIds
     */
    public function forUser(User $user, array $mantraIds): void
    {
        $this->recompute($user, null); // racha global (obligatoria)

        foreach (array_unique($mantraIds) as $mantraId) {
            $this->recompute($user, $mantraId); // por mantra (secundaria)
        }
    }

    private function recompute(User $user, ?int $mantraId): void
    {
        // mantra_key = COALESCE(mantra_id, 0): la columna generada permite
        // consultar el total del día (null) con el mismo índice único.
        $dates = $user->dailyAggregates()
            ->where('mantra_key', $mantraId ?? 0)
            ->where('recitations', '>', 0)
            ->orderBy('local_date')
            ->pluck('local_date')
            ->map(fn ($date) => substr((string) $date, 0, 10))
            ->unique()
            ->values();

        if ($dates->isEmpty()) {
            $this->store($user, $mantraId, 0, 0, null);

            return;
        }

        $max = 1;
        $run = 1;

        for ($i = 1; $i < $dates->count(); $i++) {
            $expected = Carbon::parse($dates[$i - 1])->addDay()->toDateString();
            $run = $dates[$i] === $expected ? $run + 1 : 1;
            $max = max($max, $run);
        }

        // "Hoy" en la timezone del usuario, solo para anclar la racha actual.
        try {
            $today = now($user->timezone ?: config('app.timezone'));
        } catch (\Throwable) {
            $today = now();
        }

        $last = $dates->last();
        $isCurrent = $last === $today->toDateString()
            || $last === $today->copy()->subDay()->toDateString();

        $this->store($user, $mantraId, $isCurrent ? $run : 0, $max, $last);
    }

    private function store(
        User $user,
        ?int $mantraId,
        int $current,
        int $max,
        ?string $last,
    ): void {
        Streak::createOrFirst([
            'user_id' => $user->id,
            'mantra_id' => $mantraId,
        ])->update([
            'current_count' => $current,
            'max_count' => $max,
            'last_local_date' => $last,
        ]);
    }
}
