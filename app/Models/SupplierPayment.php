<?php

namespace App\Models;

use App\Enums\SupplierPaymentStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $company_id
 * @property-read int $supplier_id
 * @property-read int $fiscal_year_id
 * @property-read int $accounting_period_id
 * @property-read int $payment_method_id
 * @property-read int $journal_id
 * @property-read int $destination_account_id
 * @property-read string $payment_number
 * @property-read Carbon $payment_date
 * @property-read numeric-string $amount
 * @property-read string $currency
 * @property-read string|null $reference
 * @property-read string|null $notes
 * @property-read SupplierPaymentStatus $status
 * @property-read int $created_by
 * @property-read Carbon|null $posted_at
 * @property-read int|null $journal_entry_id
 */
class SupplierPayment extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'supplier_id',
        'fiscal_year_id',
        'accounting_period_id',
        'payment_method_id',
        'journal_id',
        'destination_account_id',
        'payment_number',
        'payment_date',
        'amount',
        'currency',
        'reference',
        'notes',
        'status',
        'created_by',
        'posted_at',
        'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'status' => SupplierPaymentStatus::class,
            'amount' => 'decimal:3',
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
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * @return BelongsTo<Journal, $this>
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /**
     * Bank or cash account from which the money was paid.
     *
     * @return BelongsTo<Account, $this>
     */
    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'destination_account_id');
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
     * @return HasMany<SupplierPaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }

    /** @return numeric-string */
    public function allocatedAmount(): string
    {
        $allocated = $this->allocations->sum('amount');

        return number_format((float) str_replace(',', '', (string) $allocated), 3, '.', '');
    }

    /** @return numeric-string */
    public function unallocatedAmount(): string
    {
        $unallocated = bcsub((string) $this->amount, $this->allocatedAmount(), 3);

        return bccomp($unallocated, '0', 3) < 0 ? '0.000' : $unallocated;
    }

    public function isDraft(): bool
    {
        return $this->status === SupplierPaymentStatus::DRAFT;
    }

    public function isPosted(): bool
    {
        return $this->status === SupplierPaymentStatus::POSTED;
    }
}
