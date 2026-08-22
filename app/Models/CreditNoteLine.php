<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read int $credit_note_id
 * @property-read int|null $invoice_line_id
 * @property-read int $product_id
 * @property-read string $description
 * @property-read numeric-string $quantity
 * @property-read string $unit
 * @property-read numeric-string $unit_price
 * @property-read numeric-string $discount_percent
 * @property-read numeric-string $discount_amount
 * @property-read int|null $tax_rate_id
 * @property-read string|null $tax_code
 * @property-read numeric-string $tax_rate
 * @property-read numeric-string $tax_amount
 * @property-read numeric-string $line_subtotal
 * @property-read numeric-string $line_total
 * @property-read int|null $sales_account_id
 * @property-read int $sort_order
 */
class CreditNoteLine extends Model
{
    protected $fillable = [
        'credit_note_id',
        'invoice_line_id',
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
     * @return BelongsTo<CreditNote, $this>
     */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    /**
     * @return BelongsTo<InvoiceLine, $this>
     */
    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(InvoiceLine::class);
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
        return $this->belongsTo(Account::class);
    }
}
