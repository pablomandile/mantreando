<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Racha por usuario. mantra_id = null es la racha global.
 * La lógica de actualización llega en la etapa de estadísticas.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $mantra_id
 * @property int $current_count
 * @property int $max_count
 * @property string|null $last_local_date sin cast, string 'Y-m-d'
 */
#[Fillable(['user_id', 'mantra_id', 'current_count', 'max_count', 'last_local_date'])]
class Streak extends Model
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
