<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Veces que un usuario recitó una recitación en un día.
 *
 * @property int $id
 * @property int $user_id
 * @property int $recitation_id
 * @property string $local_date sin cast, string 'Y-m-d'
 * @property int $count
 */
#[Fillable(['user_id', 'recitation_id', 'local_date', 'count'])]
class RecitationLog extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Recitation, $this> */
    public function recitation(): BelongsTo
    {
        return $this->belongsTo(Recitation::class);
    }
}
