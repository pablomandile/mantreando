<?php

namespace App\Events;

use App\Models\PracticeSession;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Collection;

class PracticeSessionsRecorded
{
    use Dispatchable;

    /**
     * @param  Collection<int, PracticeSession>  $sessions  Solo sesiones NUEVAS
     *                                                      (los duplicados por uuid nunca llegan acá — de ahí la idempotencia
     *                                                      de los agregados).
     */
    public function __construct(
        public Collection $sessions,
    ) {}
}
