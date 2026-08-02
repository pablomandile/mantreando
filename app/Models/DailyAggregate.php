<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Agregado diario precalculado. mantra_id = null es el total del día.
 * `mantra_key` es una columna generada (COALESCE(mantra_id, 0)) — no se escribe.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $mantra_id
 * @property int $mantra_key
 * @property string $local_date  sin cast, string 'Y-m-d'
 * @property int $recitations
 * @property int $malas
 * @property int $duration_seconds
 * @property int $sessions_count
 */
#[Fillable([
    'user_id', 'mantra_id', 'local_date',
    'recitations', 'malas', 'duration_seconds', 'sessions_count',
])]
class DailyAggregate extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Mantra, $this> */
    public function mantra(): BelongsTo
    {
        return $this->belongsTo(Mantra::class);
    }
}
