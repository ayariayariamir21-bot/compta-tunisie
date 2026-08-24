<?php

namespace App\Models;

use App\Enums\CompanyRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser as FortifyPasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passkeys\Contracts\PasskeyUser;

class User extends Authenticatable implements FortifyPasskeyUser, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * Attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))
            ->filter()
            ->map(fn ($word) => strtoupper(substr($word, 0, 1)))
            ->take(2)
            ->implode('');
    }

    /**
     * Companies associated with this user.
     *
     * @return BelongsToMany<Company, $this>
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->withPivot([
                'role',
                'is_active',
            ])
            ->withTimestamps();
    }

    /**
     * Whether the user holds an ACTIVE membership in the company,
     * regardless of the stored role value.
     */
    public function isActiveCompanyMember(int|Company $company): bool
    {
        return $this->companies()
            ->whereKey($company instanceof Company ? $company->getKey() : $company)
            ->wherePivot('is_active', true)
            ->exists();
    }

    public function hasCompanyRole(int|Company $company, CompanyRole $role): bool
    {
        return $this->hasAnyCompanyRole($company, $role);
    }

    public function hasAnyCompanyRole(int|Company $company, CompanyRole ...$roles): bool
    {
        if ($roles === []) {
            return false;
        }

        return $this->companies()
            ->whereKey($company instanceof Company ? $company->getKey() : $company)
            ->wherePivot('is_active', true)
            ->wherePivotIn('role', array_map(fn (CompanyRole $role) => $role->value, $roles))
            ->exists();
    }

    public function isCompanyAdmin(int|Company $company): bool
    {
        return $this->hasAnyCompanyRole($company, CompanyRole::Admin);
    }

    /**
     * Whether the user holds any of the given roles in at least one active
     * company, regardless of which company. Used for system-level resources
     * (backups) that span all companies.
     */
    public function hasRoleAnywhere(CompanyRole ...$roles): bool
    {
        if ($roles === []) {
            return false;
        }

        return $this->companies()
            ->wherePivot('is_active', true)
            ->wherePivotIn('role', array_map(fn (CompanyRole $role) => $role->value, $roles))
            ->exists();
    }

    public function isCompanyAdminAnywhere(): bool
    {
        return $this->hasRoleAnywhere(CompanyRole::Admin);
    }

    /**
     * @return HasMany<JournalEntry, $this>
     */
    public function createdJournalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'created_by');
    }

    /**
     * @return HasMany<Quote, $this>
     */
    public function createdQuotes(): HasMany
    {
        return $this->hasMany(Quote::class, 'created_by');
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function createdInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'created_by');
    }
}
