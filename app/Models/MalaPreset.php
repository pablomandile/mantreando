<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $material wood|bodhi|red|blue
 * @property string|null $tassel_color null = sigue al material de las cuentas
 * @property string|null $texture_path
 * @property bool $is_active
 * @property-read string|null $texture_url
 */
#[Fillable(['user_id', 'name', 'material', 'tassel_color', 'texture_path', 'is_active'])]
class MalaPreset extends Model
{
    public const MATERIALS = ['wood', 'bodhi', 'red', 'blue'];

    /**
     * Colores de borla ofrecidos en Mi mala. Acá viven solo las claves: los
     * hex están en resources/js/lib/mala/tassel.ts, que es donde se pintan
     * (la borla y su muestra en los ajustes). Una sola fuente para el color.
     */
    public const TASSEL_COLORS = ['saffron', 'crimson', 'jade', 'indigo', 'rose', 'ivory'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return Attribute<string|null, mixed> */
    protected function textureUrl(): Attribute
    {
        return Attribute::make(get: fn (): ?string => $this->texture_path !== null
            ? Storage::disk('public')->url($this->texture_path)
            : null);
    }
}
