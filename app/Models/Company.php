<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'legal_name',
        'tax_identifier',
        'registration_number',
        'legal_form',
        'address',
        'city',
        'postal_code',
        'country',
        'phone',
        'email',
        'currency',
        'fiscal_year_start',
        'fiscal_year_end',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year_start' => 'date',
            'fiscal_year_end' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot([
                'role',
                'is_active',
            ])
            ->withTimestamps();
    }

    public function fiscalYears(): HasMany
    {
        return $this->hasMany(FiscalYear::class);
    }

    public function journals(): HasMany
    {
        return $this->hasMany(Journal::class);
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function accountingSettings(): HasOne
    {
        return $this->hasOne(CompanyAccountingSetting::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function taxRates(): HasMany
    {
        return $this->hasMany(TaxRate::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
