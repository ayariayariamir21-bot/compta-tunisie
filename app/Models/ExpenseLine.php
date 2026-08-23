<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseLine extends Model
{
    protected $fillable = [
        'expense_id',
        'expense_account_id',
        'label',
        'quantity',
        'unit_price',
        'discount_percent',
        'gross_amount',
        'discount_amount',
        'line_subtotal',
        'tax_rate_id',
        'tax_code',
        'tax_rate',
        'tax_amount',
        'line_total',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:3',
            'discount_percent' => 'decimal:3',
            'gross_amount' => 'decimal:3',
            'discount_amount' => 'decimal:3',
            'line_subtotal' => 'decimal:3',
            'tax_rate' => 'decimal:3',
            'tax_amount' => 'decimal:3',
            'line_total' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<Expense, $this>
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    /**
     * @return BelongsTo<TaxRate, $this>
     */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }
}
