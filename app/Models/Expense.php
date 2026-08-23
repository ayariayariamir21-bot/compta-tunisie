<?php

namespace App\Models;

use App\Enums\ExpenseStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $company_id
 * @property-read int|null $supplier_id
 * @property-read int $fiscal_year_id
 * @property-read int $accounting_period_id
 * @property-read int $journal_id
 * @property-read int|null $payment_method_id
 * @property-read string $expense_number
 * @property-read Carbon $expense_date
 * @property-read Carbon|null $due_date
 * @property-read ExpenseStatus $status
 * @property-read string $currency
 * @property-read string $subtotal
 * @property-read string $discount_total
 * @property-read string $tax_total
 * @property-read string $total
 * @property-read string|null $reference
 * @property-read string|null $description
 * @property-read string|null $notes
 * @property-read int $created_by
 * @property-read Carbon|null $posted_at
 * @property-read int|null $journal_entry_id
 */
class Expense extends Model
{
    protected $fillable = [
        'company_id',
        'supplier_id',
        'fiscal_year_id',
        'accounting_period_id',
        'journal_id',
        'payment_method_id',
        'expense_number',
        'expense_date',
        'due_date',
        'status',
        'currency',
        'subtotal',
        'discount_total',
        'tax_total',
        'total',
        'reference',
        'description',
        'notes',
        'created_by',
        'posted_at',
        'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'due_date' => 'date',
            'status' => ExpenseStatus::class,
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
     * @return BelongsTo<Journal, $this>
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /**
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
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
     * @return HasMany<ExpenseLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ExpenseLine::class)->orderBy('sort_order');
    }

    public function isDraft(): bool
    {
        return $this->status === ExpenseStatus::DRAFT;
    }
}
