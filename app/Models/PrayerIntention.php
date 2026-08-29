<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PrayerIntentionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Una persona por la que ora el usuario, con sus motivos.
 *
 * user_id y archived_at quedan fuera de Fillable a propósito: el dueño sale
 * de la relación (nunca del formulario) y la fecha de archivo la pone la
 * acción de archivar, no un campo del request.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $custom_reason texto del motivo "Otro"
 * @property CarbonImmutable|null $archived_at null = sigue en la lista activa
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'custom_reason'])]
class PrayerIntention extends Model
{
    /** @use HasFactory<PrayerIntentionFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<PrayerReason, $this> */
    public function reasons(): BelongsToMany
    {
        return $this->belongsToMany(PrayerReason::class, 'prayer_intention_reason');
    }
}
