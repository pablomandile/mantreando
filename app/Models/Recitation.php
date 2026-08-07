<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Oración o yoga que se lee (no se cuenta en el mala). Ver la migración
 * para por qué vive fuera de mantras.
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string $text
 * @property int $position
 */
#[Fillable(['slug', 'title', 'text', 'position'])]
class Recitation extends Model
{
    public $timestamps = true;

    /** Usuarios con compromiso fijado sobre esta recitación. */
    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('daily_commitment')
            ->withTimestamps();
    }

    /** @return HasMany<RecitationLog, $this> */
    public function logs(): HasMany
    {
        return $this->hasMany(RecitationLog::class);
    }
}
