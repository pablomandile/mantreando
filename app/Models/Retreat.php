<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\RetreatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * El retiro de aproximación de un usuario sobre una deidad.
 *
 * Hay una fila por (usuario, deidad) y se practica una por vez: la activa es
 * la que tiene is_active. Cambiar de deidad no borra nada, solo cambia cuál
 * está al frente.
 *
 * @property int $id
 * @property int $user_id
 * @property int $retreat_deity_id
 * @property CarbonImmutable $started_on
 * @property CarbonImmutable|null $completed_on
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['retreat_deity_id', 'started_on', 'completed_on', 'is_active'])]
class Retreat extends Model
{
    /** @use HasFactory<RetreatFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'completed_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<RetreatDeity, $this> */
    public function deity(): BelongsTo
    {
        return $this->belongsTo(RetreatDeity::class, 'retreat_deity_id');
    }

    /** @return HasMany<RetreatProgress, $this> */
    public function progress(): HasMany
    {
        return $this->hasMany(RetreatProgress::class);
    }

    /**
     * La etapa en curso: la primera del orden que el usuario todavía no cerró.
     * Null cuando ya cerró todas, o cuando la deidad no tiene mantras
     * cargados todavía.
     */
    public function currentStage(): ?RetreatMantra
    {
        $closed = $this->progress()
            ->whereNotNull('completed_on')
            ->pluck('retreat_mantra_id')
            ->all();

        return $this->deity->mantras()
            ->whereNotIn('id', $closed)
            ->first();
    }
}
