<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $company_id
 * @property-read int $customer_id
 * @property-read int $fiscal_year_id
 * @property-read int $accounting_period_id
 * @property-read int $journal_id
 * @property-read int|null $quote_id
 * @property-read string $invoice_number
 * @property-read Carbon $invoice_date
 * @property-read Carbon|null $due_date
 * @property-read InvoiceStatus $status
 * @property-read string $currency
 * @property-read string $subtotal
 * @property-read string $discount_total
 * @property-read string $tax_total
 * @property-read string $total
 * @property-read int $payment_terms_days
 * @property-read string|null $notes
 * @property-read string|null $terms
 * @property-read int $created_by
 * @property-read Carbon|null $posted_at
 * @property-read int|null $journal_entry_id
 */
class Invoice extends Model
{
    protected $fillable = [
        'company_id',
        'customer_id',
        'fiscal_year_id',
        'accounting_period_id',
        'journal_id',
        'quote_id',
        'invoice_number',
        'invoice_date',
        'due_date',
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
        'posted_at',
        'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'status' => InvoiceStatus::class,
            'subtotal' => 'decimal:3',
            'discount_total' => 'decimal:3',
            'tax_total' => 'decimal:3',
            'total' => 'decimal:3',
            'payment_terms_days' => 'integer',
            'posted_at' => 'datetime',
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
     * @return BelongsTo<FiscalYear, $this>
     */
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    /**
     * @return BelongsTo<AccountingPeriod, $this>
     */
    public function accountingPeriod(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class);
    }

    /**
     * @return BelongsTo<Journal, $this>
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /**
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort_order');
    }
}
