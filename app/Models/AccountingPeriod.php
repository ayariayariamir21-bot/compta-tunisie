<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Accounting period within a fiscal year.
 *
 * State machine (two states, one terminal):
 *
 *   Ouverte   (is_open=true,  is_closed=false) — active, entries may be posted
 *   Clôturée  (is_open=false, is_closed=true)  — terminal, irreversible
 *
 * Lifecycle:
 *   1. Created as Ouverte when a FiscalYear is created (auto-generated monthly).
 *   2. Closed via AccountingPeriodPolicy::close() — sets is_open=false, is_closed=true.
 *   3. Once Clôturée, the period can NEVER be reopened through the normal UI.
 *
 * Context hierarchy:
 *   CurrentCompany → CurrentFiscalYear → CurrentAccountingPeriod
 *
 * Switching company clears both fiscal year and period session keys.
 * Switching fiscal year clears the period session key.
 */
class AccountingPeriod extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'fiscal_year_id',
        'name',
        'code',
        'start_date',
        'end_date',
        'is_open',
        'is_closed',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_open' => 'boolean',
            'is_closed' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<FiscalYear, $this>
     */
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    /**
     * @return HasMany<JournalEntry, $this>
     */
    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<CreditNote, $this>
     */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    /**
     * @return HasMany<CustomerPayment, $this>
     */
    public function customerPayments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    /**
     * @return HasMany<SupplierPayment, $this>
     */
    public function supplierPayments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
