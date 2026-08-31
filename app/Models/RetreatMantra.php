<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\RetreatMantraFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una etapa del retiro: el mantra que se recita y cuántas veces.
 *
 * El texto es propio del retiro y no sale de la biblioteca de mantras: son
 * recitaciones distintas (las cien sílabas, el mantra corto, la sílaba
 * semilla) y la cifra de cada una la carga el administrador.
 *
 * @property int $id
 * @property int $retreat_deity_id
 * @property int $position
 * @property string $name
 * @property string $text
 * @property int $goal
 * @property-read int|null $progress_count
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['retreat_deity_id', 'position', 'name', 'text', 'goal'])]
class RetreatMantra extends Model
{
    /** @use HasFactory<RetreatMantraFactory> */
    use HasFactory;

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
}
