<?php

namespace App\Models;

use App\Enums\PracticeMode;
use Database\Factories\PracticeSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Evento append-only e inmutable. El uuid lo genera el cliente; la
 * sincronización hace insert-or-ignore por uuid (nunca update).
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int $mantra_id
 * @property PracticeMode $mode
 * @property int $recitations
 * @property int $completed_malas
 * @property Carbon $started_at
 * @property Carbon $ended_at
 * @property int $duration_seconds
 * @property string $local_date SIN cast: siempre string 'Y-m-d' calculada en
 *                              el dispositivo — castearla a Carbon invita a
 *                              corrimientos de timezone.
 * @property Carbon $synced_at
 */
#[Fillable([
    'uuid', 'user_id', 'mantra_id', 'mode', 'recitations', 'completed_malas',
    'started_at', 'ended_at', 'duration_seconds', 'local_date', 'synced_at',
])]
class PracticeSession extends Model
{
    /** @use HasFactory<PracticeSessionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'mode' => PracticeMode::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

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
