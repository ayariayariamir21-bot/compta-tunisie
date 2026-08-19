<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Company-level accounting settings.
 *
 * Each company has exactly one settings record (company_id is unique).
 * These settings define defaults for journals, accounts, currencies, and numbering.
 */
class CompanyAccountingSetting extends Model
{
    use HasFactory;

    protected $table = 'company_accounting_settings';

    protected $fillable = [
        'company_id',
        'default_currency',
        'decimal_precision',
        'fiscal_year_start_month',
        'default_sales_journal_id',
        'default_purchase_journal_id',
        'default_bank_journal_id',
        'default_cash_journal_id',
        'default_misc_journal_id',
        'default_customer_account_id',
        'default_supplier_account_id',
        'default_sales_account_id',
        'default_purchase_account_id',
        'default_bank_account_id',
        'default_cash_account_id',
        'invoice_prefix',
        'invoice_next_number',
        'quote_prefix',
        'quote_next_number',
    ];

    protected function casts(): array
    {
        return [
            'decimal_precision' => 'integer',
            'fiscal_year_start_month' => 'integer',
            'invoice_next_number' => 'integer',
            'quote_next_number' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function defaultSalesJournal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'default_sales_journal_id');
    }

    public function defaultPurchaseJournal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'default_purchase_journal_id');
    }

    public function defaultBankJournal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'default_bank_journal_id');
    }

    public function defaultCashJournal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'default_cash_journal_id');
    }

    public function defaultMiscJournal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'default_misc_journal_id');
    }

    public function defaultCustomerAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_customer_account_id');
    }

    public function defaultSupplierAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_supplier_account_id');
    }

    public function defaultSalesAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_sales_account_id');
    }

    public function defaultPurchaseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_purchase_account_id');
    }

    public function defaultBankAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_bank_account_id');
    }

    public function defaultCashAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_cash_account_id');
    }
}
