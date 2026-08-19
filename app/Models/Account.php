<?php

namespace App\Models;

use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Hierarchical chart of accounts for a specific company and fiscal year.
 *
 * Accounts form a tree via the self-referencing parent_id FK.
 * The combination (company_id, fiscal_year_id, code) is unique.
 */
class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'fiscal_year_id',
        'parent_id',
        'code',
        'name',
        'account_type',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'account_type' => AccountType::class,
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    public function isDescendantOf(int $accountId): bool
    {
        $current = $this->parent;

        while ($current) {
            if ($current->id === $accountId) {
                return true;
            }

            $current = $current->parent;
        }

        return false;
    }

    public function journalEntryLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
