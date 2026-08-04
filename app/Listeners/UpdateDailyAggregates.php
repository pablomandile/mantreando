<?php

namespace App\Listeners;

use App\Actions\Practice\UpdateStreaks;
use App\Events\PracticeSessionsRecorded;
use App\Models\DailyAggregate;
use App\Models\PracticeSession;

class UpdateDailyAggregates
{
    public function __construct(
        private UpdateStreaks $updateStreaks,
    ) {}

    /**
     * Incrementa los agregados diarios por cada sesión nueva: una fila por
     * (usuario, mantra, día) y una fila total-del-día con mantra_id = null.
     * El dashboard lee siempre de acá, nunca suma sesiones crudas.
     * Después recalcula las rachas afectadas.
     */
    public function handle(PracticeSessionsRecorded $event): void
    {
        foreach ($event->sessions as $session) {
            $this->increment($session, $session->mantra_id);
            $this->increment($session, null); // total del día
        }

        $first = $event->sessions->first();

        if ($first !== null) {
            // El batch siempre es de un solo usuario (viene del sync auth).
            $this->updateStreaks->forUser(
                $first->user,
                $event->sessions
                    ->pluck('mantra_id')
                    ->map(fn (mixed $id): int => (int) $id)
                    ->values()
                    ->all(),
            );
        }
    }

    private function increment(PracticeSession $session, ?int $mantraId): void
    {
        // createOrFirst: INSERT primero y, si choca con el unique
        // (user_id, mantra_key, local_date), recupera la fila existente.
        $aggregate = DailyAggregate::createOrFirst([
            'user_id' => $session->user_id,
            'mantra_id' => $mantraId,
            'local_date' => $session->local_date,
        ]);

        // Un solo UPDATE atómico para los cuatro contadores. incrementEach
        // en vez de armar el SQL a mano: los valores van como bindings.
        DailyAggregate::whereKey($aggregate->getKey())->incrementEach([
            'recitations' => $session->recitations,
            'malas' => $session->completed_malas,
            'duration_seconds' => $session->duration_seconds,
            'sessions_count' => 1,
        ]);
    }
}
