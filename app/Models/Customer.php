<?php

namespace App\Models;

use App\Enums\CustomerType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'account_id',
        'code',
        'name',
        'legal_name',
        'customer_type',
        'tax_identifier',
        'rne',
        'address',
        'postal_code',
        'city',
        'governorate',
        'country',
        'phone',
        'mobile',
        'email',
        'website',
        'payment_terms_days',
        'credit_limit',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'customer_type' => CustomerType::class,
            'payment_terms_days' => 'integer',
            'credit_limit' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return HasMany<Quote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }
}
