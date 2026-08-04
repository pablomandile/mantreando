<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password  null = cuenta creada vía Google
 * @property string|null $google_id
 * @property string|null $avatar
 * @property string|null $timezone  IANA; toda lógica de "día" usa local_date del dispositivo
 * @property string $locale
 * @property string $theme
 * @property array|null $settings
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'google_id', 'avatar', 'timezone', 'locale', 'theme', 'settings'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /** @var list<string> */
    protected $appends = ['avatar_url'];

    /**
     * Español por default (espeja el default de la columna): una instancia
     * recién creada ya reporta 'es' antes de tocar la base.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'locale' => 'es',
    ];

    /**
     * URL del avatar: puede ser externa (Google) o un path local subido.
     */
    protected function avatarUrl(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::get(function (): ?string {
            if ($this->avatar === null) {
                return null;
            }

            return str_starts_with($this->avatar, 'http')
                ? $this->avatar
                : \Illuminate\Support\Facades\Storage::disk('public')->url($this->avatar);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    /** Mantras propios del usuario (no incluye los del sistema). */
    /** @return HasMany<Mantra, $this> */
    public function ownMantras(): HasMany
    {
        return $this->hasMany(Mantra::class);
    }

    /** Preferencias por mantra (favoritos, compromisos) vía pivot. */
    /** @return BelongsToMany<Mantra, $this> */
    public function mantras(): BelongsToMany
    {
        return $this->belongsToMany(Mantra::class)
            ->withPivot(['is_favorite', 'daily_commitment', 'total_goal', 'position'])
            ->withTimestamps();
    }

    /** @return HasMany<PracticeSession, $this> */
    public function practiceSessions(): HasMany
    {
        return $this->hasMany(PracticeSession::class);
    }

    /** @return HasMany<DailyAggregate, $this> */
    public function dailyAggregates(): HasMany
    {
        return $this->hasMany(DailyAggregate::class);
    }

    /** @return HasMany<Streak, $this> */
    public function streaks(): HasMany
    {
        return $this->hasMany(Streak::class);
    }
}
