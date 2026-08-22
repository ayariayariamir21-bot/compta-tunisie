<?php

namespace App\Models;

use App\Enums\CreditNoteStatus;
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
 * @property-read int $invoice_id
 * @property-read string $credit_note_number
 * @property-read Carbon $credit_note_date
 * @property-read string|null $reason
 * @property-read CreditNoteStatus $status
 * @property-read string $currency
 * @property-read numeric-string $subtotal
 * @property-read numeric-string $discount_total
 * @property-read numeric-string $tax_total
 * @property-read numeric-string $total
 * @property-read string|null $notes
 * @property-read int $created_by
 * @property-read Carbon|null $posted_at
 * @property-read int|null $journal_entry_id
 */
class CreditNote extends Model
{
    protected $fillable = [
        'company_id',
        'customer_id',
        'fiscal_year_id',
        'accounting_period_id',
        'journal_id',
        'invoice_id',
        'credit_note_number',
        'credit_note_date',
        'reason',
        'status',
        'currency',
        'subtotal',
        'discount_total',
        'tax_total',
        'total',
        'notes',
        'created_by',
        'posted_at',
        'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'credit_note_date' => 'date',
            'status' => CreditNoteStatus::class,
            'subtotal' => 'decimal:3',
            'discount_total' => 'decimal:3',
            'tax_total' => 'decimal:3',
            'total' => 'decimal:3',
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
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
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
     * @return HasMany<CreditNoteLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(CreditNoteLine::class)->orderBy('sort_order');
    }
}
