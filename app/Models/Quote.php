<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $company_id
 * @property-read int $customer_id
 * @property-read string $quote_number
 * @property-read Carbon $quote_date
 * @property-read Carbon|null $valid_until
 * @property-read QuoteStatus $status
 * @property-read string $currency
 * @property-read string $subtotal
 * @property-read string $discount_total
 * @property-read string $tax_total
 * @property-read string $total
 * @property-read int $payment_terms_days
 * @property-read string|null $notes
 * @property-read string|null $terms
 * @property-read int $created_by
 */
class Quote extends Model
{
    protected $fillable = [
        'company_id',
        'customer_id',
        'quote_number',
        'quote_date',
        'valid_until',
        'status',
        'currency',
        'subtotal',
        'discount_total',
        'tax_total',
        'total',
        'payment_terms_days',
        'notes',
        'terms',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quote_date' => 'date',
            'valid_until' => 'date',
            'status' => QuoteStatus::class,
            'subtotal' => 'decimal:3',
            'discount_total' => 'decimal:3',
            'tax_total' => 'decimal:3',
            'total' => 'decimal:3',
            'payment_terms_days' => 'integer',
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
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<QuoteLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLine::class)->orderBy('sort_order');
    }

    /**
     * @return HasOne<Invoice, $this>
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }
}
