<?php

namespace App\Models;

use Database\Factories\MantraCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property array<string, string> $name {"es": "...", "en": "..."}
 * @property string $slug
 * @property int $position
 * @property-read string $localized_name
 */
#[Fillable(['name', 'slug', 'position'])]
class MantraCategory extends Model
{
    /** @use HasFactory<MantraCategoryFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'name' => 'array',
        ];
    }

    /** @return HasMany<Mantra, $this> */
    public function mantras(): HasMany
    {
        return $this->hasMany(Mantra::class, 'category_id');
    }

    /**
     * Nombre en el locale actual, con fallback a español y luego al primero disponible.
     *
     * @return Attribute<string, mixed>
     */
    protected function localizedName(): Attribute
    {
        return Attribute::make(get: function (): string {
            $names = $this->name ?? [];

            return $names[app()->getLocale()]
                ?? $names['es']
                ?? (reset($names) ?: '');
        });
    }
}
