<?php

namespace App\Models;

use App\Enums\CustomerPaymentStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
 * @property-read int $payment_method_id
 * @property-read int $journal_id
 * @property-read int $destination_account_id
 * @property-read string $payment_number
 * @property-read Carbon $payment_date
 * @property-read numeric-string $amount
 * @property-read string $currency
 * @property-read string|null $reference
 * @property-read string|null $notes
 * @property-read CustomerPaymentStatus $status
 * @property-read int $created_by
 * @property-read Carbon|null $posted_at
 * @property-read int|null $journal_entry_id
 */
class CustomerPayment extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'customer_id',
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
            'status' => CustomerPaymentStatus::class,
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
     * Bank or cash account where the money was received.
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
     * @return HasMany<CustomerPaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(CustomerPaymentAllocation::class);
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
        return $this->status === CustomerPaymentStatus::DRAFT;
    }

    public function isPosted(): bool
    {
        return $this->status === CustomerPaymentStatus::POSTED;
    }
}
