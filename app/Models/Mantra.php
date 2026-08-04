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
    'text', 'translation', 'description', 'benefits', 'image_path', 'translations',
])]
class Mantra extends Model
{
    /** @use HasFactory<MantraFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'translations' => 'array',
        ];
    }

    /**
     * Campo localizado: la traducción del locale actual si existe
     * (mantras del sistema), o la columna base en español.
     */
    public function localized(string $field): ?string
    {
        return data_get($this->translations, app()->getLocale().'.'.$field)
            ?? $this->{$field};
    }

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
            ->withPivot(['is_favorite', 'daily_commitment', 'total_goal', 'position'])
            ->withTimestamps();
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<PracticeSession, $this> */
    public function practiceSessions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PracticeSession::class);
    }

    /** Mantras del sistema (compartidos, sin dueño). */
    public function scopeSystem(Builder $query): Builder
    {
        return $query->whereNull('mantras.user_id');
    }

    /**
     * Mantras visibles para un usuario: los del sistema más los propios.
     * Columnas calificadas: el índice y el bootstrap joinean mantra_user
     * (orden personal) y user_id sería ambiguo.
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->whereNull('mantras.user_id')->orWhere('mantras.user_id', $user->id);
        });
    }

    public function isSystem(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Prefijo de las imágenes que viajan con la app (public/img), usadas por
     * los mantras del sistema. Todo lo demás es una subida del usuario y vive
     * en el disco public (storage/app/public), que no está en el repo.
     */
    private const APP_IMAGE_PREFIX = 'img/';

    public function hasAppImage(): bool
    {
        return $this->image_path !== null
            && str_starts_with($this->image_path, self::APP_IMAGE_PREFIX);
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if ($this->image_path === null) {
                return null;
            }

            return $this->hasAppImage()
                ? asset($this->image_path)
                : Storage::disk('public')->url($this->image_path);
        });
    }

    /**
     * Miniatura para las tarjetas de la lista. Las imágenes de la app traen
     * una versión cuadrada de 128 px al lado (img/budas/thumb/x.jpg); las que
     * sube el usuario no, y reusan la original.
     */
    protected function imageThumbUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->hasAppImage()) {
                return $this->image_url;
            }

            return asset(dirname($this->image_path).'/thumb/'.basename($this->image_path));
        });
    }
}
