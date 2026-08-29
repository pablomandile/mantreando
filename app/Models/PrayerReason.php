<?php

namespace App\Models;

use App\Enums\MantraColor;
use Carbon\CarbonImmutable;
use Database\Factories\PrayerReasonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Motivo por el que se ora. Catálogo global: lo mantiene un administrador y
 * queda disponible para todas las cuentas (ver la migración).
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property MantraColor|null $color
 * @property int $position
 * @property-read int|null $intentions_count
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['slug', 'name', 'color', 'position'])]
class PrayerReason extends Model
{
    /** @use HasFactory<PrayerReasonFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'color' => MantraColor::class,
        ];
    }

    /**
     * Personas marcadas con este motivo, de todas las cuentas. Se usa para
     * saber si el motivo está en uso antes de borrarlo.
     *
     * @return BelongsToMany<PrayerIntention, $this>
     */
    public function intentions(): BelongsToMany
    {
        return $this->belongsToMany(PrayerIntention::class, 'prayer_intention_reason');
    }
}
