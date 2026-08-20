<?php

namespace App\Models;

use App\Enums\TaxType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxRate extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'rate',
        'type',
        'is_active',
        'is_default',
        'sort_order',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'type' => TaxType::class,
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
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
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<QuoteLine, $this>
     */
    public function quoteLines(): HasMany
    {
        return $this->hasMany(QuoteLine::class);
    }
}
