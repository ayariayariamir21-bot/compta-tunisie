<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    protected $fillable = [
        'invoice_id',
        'product_id',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'discount_percent',
        'discount_amount',
        'tax_rate_id',
        'tax_code',
        'tax_rate',
        'tax_amount',
        'line_subtotal',
        'line_total',
        'sales_account_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:3',
            'discount_percent' => 'decimal:3',
            'discount_amount' => 'decimal:3',
            'tax_rate' => 'decimal:3',
            'tax_amount' => 'decimal:3',
            'line_subtotal' => 'decimal:3',
            'line_total' => 'decimal:3',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
}
