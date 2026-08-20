<?php

namespace App\Models;

use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'type',
        'description',
        'unit',
        'purchase_price',
        'sale_price',
        'tax_rate_id',
        'sales_account_id',
        'purchase_account_id',
        'is_active',
        'is_sellable',
        'is_purchasable',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'purchase_price' => 'decimal:3',
            'sale_price' => 'decimal:3',
            'is_active' => 'boolean',
            'is_sellable' => 'boolean',
            'is_purchasable' => 'boolean',
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
     * @return BelongsTo<TaxRate, $this>
     */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function salesAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'sales_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function purchaseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'purchase_account_id');
    }

    /**
     * @return HasMany<QuoteLine, $this>
     */
    public function quoteLines(): HasMany
    {
        return $this->hasMany(QuoteLine::class);
    }
}
