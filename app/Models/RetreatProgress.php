<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\RetreatProgressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El conteo de una etapa dentro de un retiro.
 *
 * count es el total acumulado, no un saldo: el cliente manda el valor
 * absoluto, así que un reintento nunca duplica. Las tres líneas del ábaco son
 * sus últimas tres cifras y no se guardan.
 *
 * @property int $id
 * @property int $retreat_id
 * @property int $retreat_mantra_id
 * @property int $count
 * @property CarbonImmutable|null $completed_on
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['retreat_id', 'retreat_mantra_id', 'count', 'completed_on'])]
class RetreatProgress extends Model
{
    /** @use HasFactory<RetreatProgressFactory> */
    use HasFactory;

    protected $table = 'retreat_progress';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'completed_on' => 'date',
        ];
    }

    /** @return BelongsTo<Retreat, $this> */
    public function retreat(): BelongsTo
    {
        return $this->belongsTo(Retreat::class);
    }

    /** @return BelongsTo<RetreatMantra, $this> */
    public function mantra(): BelongsTo
    {
        return $this->belongsTo(RetreatMantra::class, 'retreat_mantra_id');
    }
}
