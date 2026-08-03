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
 * @property string $material  wood|bodhi|red|blue
 * @property string|null $texture_path
 * @property bool $is_active
 */
#[Fillable(['user_id', 'name', 'material', 'texture_path', 'is_active'])]
class MalaPreset extends Model
{
    public const MATERIALS = ['wood', 'bodhi', 'red', 'blue'];

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

    protected function textureUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->texture_path !== null
            ? Storage::disk('public')->url($this->texture_path)
            : null);
    }
}
