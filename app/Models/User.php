<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password', 'is_guest', 'phone', 'gender', 'province', 'login_method', 'email_verified_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    public const ROLE_SUPER_ADMIN = 'super-admin';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_UMPIRE = 'umpire';

    /**
     * The roles a super admin may assign when creating or editing a user.
     *
     * @var list<string>
     */
    public const ASSIGNABLE_ROLES = [self::ROLE_ADMIN, self::ROLE_UMPIRE];

    /**
     * Human-readable labels for every role slug — the single source of truth
     * shared by the seeder, the ensure-role migration, and the admin UI.
     *
     * @var array<string, string>
     */
    public const ROLE_LABELS = [
        self::ROLE_SUPER_ADMIN => 'Super Admin',
        self::ROLE_ADMIN => 'Admin',
        self::ROLE_UMPIRE => 'Umpire',
    ];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = ['is_admin', 'is_umpire', 'is_super_admin'];

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
            'is_guest' => 'boolean',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function tournamentRegistrations(): HasMany
    {
        return $this->hasMany(TournamentRegistration::class);
    }

    public function isAdminRole(): bool
    {
        return $this->roles->contains(fn (Role $role) => in_array($role->slug, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN], true));
    }

    public function isUmpire(): bool
    {
        return $this->roles->contains(fn (Role $role) => $role->slug === self::ROLE_UMPIRE);
    }

    public function isSuperAdmin(): bool
    {
        return $this->roles->contains(fn (Role $role) => $role->slug === self::ROLE_SUPER_ADMIN);
    }

    public function getIsAdminAttribute(): bool
    {
        return $this->isAdminRole();
    }

    public function getIsUmpireAttribute(): bool
    {
        return $this->isUmpire();
    }

    public function getIsSuperAdminAttribute(): bool
    {
        return $this->isSuperAdmin();
    }
}
