<?php

namespace App\Models;

use App\Enums\AdminPanel;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property bool $is_super_admin
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'is_super_admin'])]
#[Hidden(['remember_token'])]
class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The database default for is_super_admin only applies once a row is
     * written. Declaring it here means a User that has not been saved yet
     * still answers isSuperAdmin() instead of throwing under strict mode.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_super_admin' => false,
    ];

    /**
     * The restaurants this user staffs or administers.
     *
     * @return BelongsToMany<Restaurant, $this>
     */
    public function restaurants(): BelongsToMany
    {
        return $this->belongsToMany(Restaurant::class)->withTimestamps();
    }

    /**
     * The sign-in codes issued to this user.
     *
     * @return HasMany<OneTimePassword, $this>
     */
    public function oneTimePasswords(): HasMany
    {
        return $this->hasMany(OneTimePassword::class);
    }

    /**
     * Accounts hold no password: the only way in is a one-time code.
     *
     * Returning an empty string keeps every framework code path that reaches
     * for a password hash working. In particular it makes Laravel's
     * AuthenticateSession middleware fall through instead of logging the user
     * straight back out again.
     */
    public function getAuthPassword(): string
    {
        return '';
    }

    /**
     * Limit the query to the account at an address, however it was capitalised.
     *
     * Addresses are stored as they were typed, so every lookup by email has to
     * fold case or a returning user is treated as a stranger.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeWithEmail(Builder $query, string $email): void
    {
        $query->whereRaw('lower(email) = ?', [mb_strtolower($email)]);
    }

    /**
     * Whether this user administers the whole platform rather than one restaurant.
     *
     * This is a column rather than a role: platform ownership is global and
     * granted deliberately, where roles are what someone does inside a single
     * restaurant. AppServiceProvider grants a super admin every permission.
     */
    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin;
    }

    /**
     * Limit the query to platform staff.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSuperAdmins(Builder $query): void
    {
        $query->where('is_super_admin', true);
    }

    /**
     * Gate entry to each Filament panel.
     *
     * The super admin panel is reserved for platform staff. The tenant panel is
     * open to anyone attached to a restaurant, plus platform staff for support.
     * A panel this application does not know about is closed to everyone.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return match (AdminPanel::tryFrom($panel->getId())) {
            AdminPanel::SuperAdmin => $this->isSuperAdmin(),
            AdminPanel::Admin => $this->isSuperAdmin() || $this->restaurants()->exists(),
            default => false,
        };
    }

    /**
     * The tenants offered in the panel's restaurant switcher.
     *
     * @return Collection<int, Restaurant>
     */
    public function getTenants(Panel $panel): Collection
    {
        if ($this->isSuperAdmin()) {
            return Restaurant::query()->orderBy('name')->get();
        }

        return $this->restaurants;
    }

    /**
     * Guard against a user reaching another restaurant by editing the subdomain.
     */
    public function canAccessTenant(Model $tenant): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->restaurants()->whereKey($tenant)->exists();
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
            'is_super_admin' => 'boolean',
        ];
    }
}
