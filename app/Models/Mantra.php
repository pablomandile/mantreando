<?php

namespace App\Models;

use Database\Factories\MantraFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int|null $user_id  null = mantra del sistema
 * @property int $category_id
 * @property string $name
 * @property string|null $original_name
 * @property string|null $transliteration
 * @property string $text
 * @property string|null $translation
 * @property string|null $description
 * @property string|null $benefits
 * @property string|null $image_path
 */
#[Fillable([
    'user_id', 'category_id', 'name', 'original_name', 'transliteration',
    'text', 'translation', 'description', 'benefits', 'image_path',
])]
class Mantra extends Model
{
    /** @use HasFactory<MantraFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<MantraCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MantraCategory::class, 'category_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['is_favorite', 'daily_commitment', 'total_goal'])
            ->withTimestamps();
    }

    /** Mantras del sistema (compartidos, sin dueño). */
    public function scopeSystem(Builder $query): Builder
    {
        return $query->whereNull('user_id');
    }

    /** Mantras visibles para un usuario: los del sistema más los propios. */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->whereNull('user_id')->orWhere('user_id', $user->id);
        });
    }

    public function isSystem(): bool
    {
        return $this->user_id === null;
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->image_path !== null
            ? Storage::disk('public')->url($this->image_path)
            : null);
    }
}
