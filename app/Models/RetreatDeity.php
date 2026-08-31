<?php

namespace App\Models;

use App\Enums\MantraColor;
use App\Models\Concerns\ResolvesImagePath;
use Carbon\CarbonImmutable;
use Database\Factories\RetreatDeityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Deidad de un retiro de aproximación. Catálogo global: lo mantiene un
 * administrador y queda disponible para todas las cuentas (ver la migración).
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $image_path la deidad
 * @property string|null $syllable_image_path su sílaba
 * @property MantraColor|null $color
 * @property int $position
 * @property-read string|null $image_url
 * @property-read string|null $syllable_image_url
 * @property-read int|null $retreats_count
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['slug', 'name', 'image_path', 'syllable_image_path', 'color', 'position'])]
class RetreatDeity extends Model
{
    /** @use HasFactory<RetreatDeityFactory> */
    use HasFactory, ResolvesImagePath;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'color' => MantraColor::class,
        ];
    }

    /**
     * Los mantras del retiro, en el orden en que se recitan: terminada la
     * cifra de uno, sigue el siguiente.
     *
     * @return HasMany<RetreatMantra, $this>
     */
    public function mantras(): HasMany
    {
        return $this->hasMany(RetreatMantra::class)->orderBy('position')->orderBy('id');
    }

    /** @return HasMany<Retreat, $this> */
    public function retreats(): HasMany
    {
        return $this->hasMany(Retreat::class);
    }

    /** @return Attribute<string|null, mixed> */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(get: fn (): ?string => $this->resolveImageUrl($this->image_path));
    }

    /** @return Attribute<string|null, mixed> */
    protected function syllableImageUrl(): Attribute
    {
        return Attribute::make(get: fn (): ?string => $this->resolveImageUrl($this->syllable_image_path));
    }
}
