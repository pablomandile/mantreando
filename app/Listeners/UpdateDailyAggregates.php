<?php

namespace App\Listeners;

use App\Events\PracticeSessionsRecorded;
use App\Models\DailyAggregate;
use App\Models\PracticeSession;
use Illuminate\Support\Facades\DB;

class UpdateDailyAggregates
{
    /**
     * Incrementa los agregados diarios por cada sesión nueva: una fila por
     * (usuario, mantra, día) y una fila total-del-día con mantra_id = null.
     * El dashboard lee siempre de acá, nunca suma sesiones crudas.
     */
    public function handle(PracticeSessionsRecorded $event): void
    {
        foreach ($event->sessions as $session) {
            $this->increment($session, $session->mantra_id);
            $this->increment($session, null); // total del día
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

        // Un solo UPDATE atómico para los cuatro contadores.
        $aggregate->increment('recitations', $session->recitations, [
            'malas' => DB::raw('malas + '.(int) $session->completed_malas),
            'duration_seconds' => DB::raw('duration_seconds + '.(int) $session->duration_seconds),
            'sessions_count' => DB::raw('sessions_count + 1'),
        ]);
    }
}
