<?php

namespace App\Models;

use App\Enums\JournalEntryStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A journal entry is a double-entry accounting record.
 *
 * Each entry belongs to a company, fiscal year, accounting period, and journal.
 * It contains multiple debit/credit lines that must balance (total debit = total credit).
 * Lifecycle: draft → posted (terminal) or draft → cancelled (terminal).
 *
 * @property JournalEntryStatus $status
 */
class JournalEntry extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'fiscal_year_id',
        'accounting_period_id',
        'journal_id',
        'entry_number',
        'entry_date',
        'reference',
        'description',
        'status',
        'created_by',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'posted_at' => 'datetime',
            'status' => JournalEntryStatus::class,
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<JournalEntryLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    /** @return numeric-string */
    public function totalDebit(): string
    {
        return number_format((float) $this->lines->sum('debit'), 3, '.', '');
    }

    /** @return numeric-string */
    public function totalCredit(): string
    {
        return number_format((float) $this->lines->sum('credit'), 3, '.', '');
    }

    public function isBalanced(): bool
    {
        return bccomp($this->totalDebit(), $this->totalCredit(), 3) === 0;
    }

    public function isDraft(): bool
    {
        return $this->status === JournalEntryStatus::DRAFT;
    }

    public function isPosted(): bool
    {
        return $this->status === JournalEntryStatus::POSTED;
    }

    public function isCancelled(): bool
    {
        return $this->status === JournalEntryStatus::CANCELLED;
    }

    public function isEditable(): bool
    {
        return $this->isDraft();
    }
}
