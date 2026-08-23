<?php

use App\Enums\ExpenseStatus;
use App\Enums\JournalEntryStatus;
use App\Enums\PaymentMethodType;
use App\Livewire\Expenses\Create;
use App\Livewire\Expenses\Edit;
use App\Livewire\Expenses\Index;
use App\Livewire\Expenses\Show;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\CompanyAccountingSetting;
use App\Models\Expense;
use App\Models\ExpenseLine;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\PaymentMethod;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\Accounting\ExpensePostingService;
use App\Services\Accounting\GeneralLedgerService;
use App\Services\Accounting\JournalEntryService;
use App\Services\Accounting\SupplierPaymentPostingService;
use App\Services\ExpenseService;
use App\Services\SupplierPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->email_verified_at = now();
    $this->user->save();

    $this->company = Company::create([
        'name' => 'Test Company',
        'currency' => 'TND',
        'is_active' => true,
    ]);

    $this->company->users()->attach($this->user, ['role' => 'admin', 'is_active' => true]);

    $this->fiscalYear = FiscalYear::create([
        'company_id' => $this->company->id,
        'name' => 'Exercice 2026',
        'code' => '2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
        'is_closed' => false,
    ]);

    $this->period = AccountingPeriod::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'name' => 'Janvier 2026',
        'code' => '2026-01',
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
        'is_open' => true,
        'is_closed' => false,
    ]);

    $this->journalOD = Journal::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => 'OD',
        'name' => 'Opérations diverses',
        'type' => 'operations_diverses',
        'is_active' => true,
    ]);

    $this->journalAchats = Journal::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => 'ACH',
        'name' => 'Achats',
        'type' => 'achats',
        'is_active' => true,
    ]);

    $this->account401 = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => '401000',
        'name' => 'Fournisseurs',
        'account_type' => 'liability',
        'is_active' => true,
    ]);

    $this->account532 = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => '532000',
        'name' => 'Banques',
        'account_type' => 'asset',
        'is_active' => true,
    ]);

    $this->account571 = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => '571000',
        'name' => 'Caisse',
        'account_type' => 'asset',
        'is_active' => true,
    ]);

    $this->account607 = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => '607000',
        'name' => 'Achats de marchandises',
        'account_type' => 'expense',
        'is_active' => true,
    ]);

    $this->account613 = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => '613000',
        'name' => 'Loyers',
        'account_type' => 'expense',
        'is_active' => true,
    ]);

    $this->account4456 = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => '445600',
        'name' => 'TVA déductible',
        'account_type' => 'asset',
        'is_active' => true,
    ]);

    $this->supplier = Supplier::create([
        'company_id' => $this->company->id,
        'code' => 'FO001',
        'name' => 'Fournisseur Test',
        'supplier_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 30,
        'account_id' => $this->account401->id,
        'is_active' => true,
    ]);

    $this->supplierSansCompte = Supplier::create([
        'company_id' => $this->company->id,
        'code' => 'FO002',
        'name' => 'Fournisseur Sans Compte',
        'supplier_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 0,
        'account_id' => null,
        'is_active' => true,
    ]);

    $this->taxRate = TaxRate::create([
        'company_id' => $this->company->id,
        'code' => 'TVA19',
        'name' => 'TVA 19%',
        'type' => 'vat',
        'rate' => 19.0,
        'purchase_tax_account_id' => $this->account4456->id,
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 0,
    ]);

    $this->methodVir = PaymentMethod::create([
        'company_id' => $this->company->id,
        'code' => 'VIR',
        'name' => 'Virement bancaire',
        'type' => PaymentMethodType::BANK_TRANSFER,
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 1,
    ]);

    $this->methodEsp = PaymentMethod::create([
        'company_id' => $this->company->id,
        'code' => 'ESP',
        'name' => 'Espèces',
        'type' => PaymentMethodType::CASH,
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 2,
    ]);

    CompanyAccountingSetting::create([
        'company_id' => $this->company->id,
        'default_currency' => 'TND',
        'decimal_precision' => 3,
        'fiscal_year_start_month' => 1,
        'default_misc_journal_id' => $this->journalOD->id,
        'default_supplier_account_id' => $this->account401->id,
        'default_bank_account_id' => $this->account532->id,
        'default_cash_account_id' => $this->account571->id,
    ]);

    session([
        'current_company_id' => $this->company->id,
        'current_fiscal_year_id' => $this->fiscalYear->id,
        'current_accounting_period_id' => $this->period->id,
    ]);

    $this->actingAs($this->user);
});

// ---------- Helpers ----------

function exService(): ExpenseService
{
    return new ExpenseService(new ExpensePostingService(
        new JournalEntryService,
        new SupplierPaymentService(new SupplierPaymentPostingService(new JournalEntryService))
    ));
}

/**
 * Base expense data: rent paid in cash, one line of 1000.000 HT on 613000.
 *
 * @return array<string, mixed>
 */
function makeExpenseData($test, array $overrides = []): array
{
    return array_merge([
        'company_id' => $test->company->id,
        'supplier_id' => null,
        'fiscal_year_id' => $test->fiscalYear->id,
        'accounting_period_id' => $test->period->id,
        'journal_id' => $test->journalOD->id,
        'payment_method_id' => $test->methodEsp->id,
        'expense_date' => '2026-01-15',
        'due_date' => null,
        'reference' => 'JUST-001',
        'description' => 'Loyer bureau janvier',
        'notes' => null,
        'created_by' => $test->user->id,
        'lines' => [
            [
                'expense_account_id' => $test->account613->id,
                'label' => 'Loyer janvier',
                'quantity' => '1',
                'unit_price' => '1000.000',
                'discount_percent' => '0',
                'tax_rate_id' => null,
            ],
        ],
    ], $overrides);
}

function createDraftExpenseForTest($test, array $overrides = []): Expense
{
    return exService()->createDraft(makeExpenseData($test, $overrides));
}

function createPostedExpenseForTest($test, array $overrides = []): Expense
{
    $expense = createDraftExpenseForTest($test, $overrides);
    exService()->post($expense->fresh(), $test->user->id);

    return $expense->fresh();
}

function normalizeDecimal($value): string
{
    return number_format((float) $value, 3, '.', '');
}

// ---------- Numbering ----------

it('generates sequential expense numbers scoped per company', function () {
    $service = exService();

    expect($service->generateExpenseNumber($this->company->id))->toBe('DEP-'.now()->year.'-000001');

    createDraftExpenseForTest($this);

    expect($service->generateExpenseNumber($this->company->id))->toBe('DEP-'.now()->year.'-000002');
});

it('starts numbering independently for each company', function () {
    createDraftExpenseForTest($this);

    $other = Company::create(['name' => 'Autre Société', 'currency' => 'TND', 'is_active' => true]);

    expect(exService()->generateExpenseNumber($other->id))->toBe('DEP-'.now()->year.'-000001');
});

// ---------- createDraft ----------

it('creates a draft expense with computed totals and normalized lines', function () {
    $expense = createDraftExpenseForTest($this);

    expect($expense->status)->toBe(ExpenseStatus::DRAFT)
        ->and($expense->expense_number)->toBe('DEP-'.now()->year.'-000001')
        ->and($expense->currency)->toBe('TND')
        ->and(normalizeDecimal($expense->subtotal))->toBe('1000.000')
        ->and(normalizeDecimal($expense->discount_total))->toBe('0.000')
        ->and(normalizeDecimal($expense->tax_total))->toBe('0.000')
        ->and(normalizeDecimal($expense->total))->toBe('1000.000');

    $line = $expense->lines->first();
    expect(normalizeDecimal($line->quantity))->toBe('1.000')
        ->and(normalizeDecimal($line->gross_amount))->toBe('1000.000')
        ->and(normalizeDecimal($line->line_subtotal))->toBe('1000.000')
        ->and(normalizeDecimal($line->line_total))->toBe('1000.000')
        ->and($line->tax_code)->toBeNull()
        ->and($line->sort_order)->toBe(1);
});

it('treats an empty quantity as one', function () {
    $expense = createDraftExpenseForTest($this, [
        'lines' => [
            [
                'expense_account_id' => $this->account613->id,
                'label' => 'Frais unique',
                'quantity' => '',
                'unit_price' => '250.500',
                'discount_percent' => '0',
            ],
        ],
    ]);

    $line = $expense->lines->first();
    expect(normalizeDecimal($line->quantity))->toBe('1.000')
        ->and(normalizeDecimal($line->line_total))->toBe('250.500');
});

it('computes discount, VAT and historical tax snapshot per line', function () {
    $expense = createDraftExpenseForTest($this, [
        'payment_method_id' => null,
        'supplier_id' => $this->supplier->id,
        'description' => 'Achat fournitures',
        'lines' => [
            [
                'expense_account_id' => $this->account607->id,
                'label' => 'Cartouches',
                'quantity' => '2',
                'unit_price' => '500.000',
                'discount_percent' => '10',
                'tax_rate_id' => $this->taxRate->id,
            ],
        ],
    ]);

    $line = $expense->lines->first();
    expect(normalizeDecimal($line->gross_amount))->toBe('1000.000')
        ->and(normalizeDecimal($line->discount_amount))->toBe('100.000')
        ->and(normalizeDecimal($line->line_subtotal))->toBe('900.000')
        ->and($line->tax_code)->toBe('TVA19')
        ->and(normalizeDecimal($line->tax_rate))->toBe('19.000')
        ->and(normalizeDecimal($line->tax_amount))->toBe('171.000')
        ->and(normalizeDecimal($line->line_total))->toBe('1071.000')
        ->and(normalizeDecimal($expense->subtotal))->toBe('900.000')
        ->and(normalizeDecimal($expense->discount_total))->toBe('100.000')
        ->and(normalizeDecimal($expense->tax_total))->toBe('171.000')
        ->and(normalizeDecimal($expense->total))->toBe('1071.000');
});

it('normalizes an empty due date to null', function () {
    $data = makeExpenseData($this, ['due_date' => '']);
    $expense = exService()->createDraft($data);

    expect($expense->due_date)->toBeNull();
});

// ---------- createDraft validation ----------

it('rejects a journal that is not operations diverses', function () {
    createDraftExpenseForTest($this, ['journal_id' => $this->journalAchats->id]);
})->throws(InvalidArgumentException::class, "Le journal sélectionné n'est pas un journal d'opérations diverses.");

it('rejects an inactive supplier', function () {
    $this->supplier->update(['is_active' => false]);

    createDraftExpenseForTest($this, ['supplier_id' => $this->supplier->id]);
})->throws(InvalidArgumentException::class, 'Le fournisseur sélectionné est inactif.');

it('rejects a supplier from another company', function () {
    $other = Company::create(['name' => 'Autre', 'currency' => 'TND', 'is_active' => true]);
    $foreign = Supplier::create([
        'company_id' => $other->id,
        'code' => 'FO-X',
        'name' => 'Fournisseur Étranger',
        'supplier_type' => 'individual',
        'country' => 'TN',
        'is_active' => true,
    ]);

    createDraftExpenseForTest($this, ['payment_method_id' => null, 'supplier_id' => $foreign->id]);
})->throws(InvalidArgumentException::class, "Le fournisseur sélectionné n'appartient pas à cette société.");

it('rejects an account that is not expense-classified', function () {
    createDraftExpenseForTest($this, [
        'lines' => [[
            'expense_account_id' => $this->account401->id,
            'label' => 'Mauvais compte',
            'quantity' => '1',
            'unit_price' => '100.000',
            'discount_percent' => '0',
        ]],
    ]);
})->throws(InvalidArgumentException::class, "n'est pas un compte de charges");

it('rejects an inactive expense account', function () {
    $this->account613->update(['is_active' => false]);

    createDraftExpenseForTest($this);
})->throws(InvalidArgumentException::class, 'Le compte de charge sélectionné est inactif.');

it('rejects a closed fiscal year', function () {
    $fy = FiscalYear::create([
        'company_id' => $this->company->id,
        'name' => 'Clôturé',
        'code' => '2025',
        'start_date' => '2025-01-01',
        'end_date' => '2025-12-31',
        'is_active' => true,
        'is_closed' => true,
    ]);
    $period = AccountingPeriod::create([
        'fiscal_year_id' => $fy->id,
        'name' => 'Janvier 2025',
        'code' => '2025-01',
        'start_date' => '2025-01-01',
        'end_date' => '2025-01-31',
        'is_open' => true,
        'is_closed' => false,
    ]);

    createDraftExpenseForTest($this, ['fiscal_year_id' => $fy->id, 'accounting_period_id' => $period->id]);
})->throws(InvalidArgumentException::class, "L'exercice comptable est clôturé.");

it('rejects a closed accounting period', function () {
    $closed = AccountingPeriod::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'name' => 'Février 2026',
        'code' => '2026-02',
        'start_date' => '2026-02-01',
        'end_date' => '2026-02-28',
        'is_open' => false,
        'is_closed' => true,
    ]);

    createDraftExpenseForTest($this, ['expense_date' => '2026-02-10', 'accounting_period_id' => $closed->id]);
})->throws(InvalidArgumentException::class, 'La période comptable est clôturée.');

it('rejects an expense date outside the period', function () {
    createDraftExpenseForTest($this, ['expense_date' => '2026-02-15']);
})->throws(InvalidArgumentException::class, 'La date de la dépense doit être comprise dans la période comptable');

it('requires at least one line', function () {
    createDraftExpenseForTest($this, ['lines' => []]);
})->throws(InvalidArgumentException::class, 'La dépense doit contenir au moins une ligne.');

it('rejects a negative unit price', function () {
    createDraftExpenseForTest($this, [
        'lines' => [[
            'expense_account_id' => $this->account613->id,
            'label' => 'Ligne',
            'quantity' => '1',
            'unit_price' => '-5.000',
            'discount_percent' => '0',
        ]],
    ]);
})->throws(InvalidArgumentException::class, 'ne peut pas être négatif');

it('rejects a discount outside 0-100', function () {
    createDraftExpenseForTest($this, [
        'lines' => [[
            'expense_account_id' => $this->account613->id,
            'label' => 'Ligne',
            'quantity' => '1',
            'unit_price' => '100.000',
            'discount_percent' => '150',
        ]],
    ]);
})->throws(InvalidArgumentException::class, 'compris entre 0 et 100');

it('rejects a non-positive quantity when provided', function () {
    createDraftExpenseForTest($this, [
        'lines' => [[
            'expense_account_id' => $this->account613->id,
            'label' => 'Ligne',
            'quantity' => '0',
            'unit_price' => '100.000',
            'discount_percent' => '0',
        ]],
    ]);
})->throws(InvalidArgumentException::class, 'supérieure à zéro');

it('requires a label on each line', function () {
    createDraftExpenseForTest($this, [
        'lines' => [[
            'expense_account_id' => $this->account613->id,
            'label' => '   ',
            'quantity' => '1',
            'unit_price' => '100.000',
            'discount_percent' => '0',
        ]],
    ]);
})->throws(InvalidArgumentException::class, 'doit avoir un libellé');

it('rejects an inactive tax rate', function () {
    $inactive = TaxRate::create([
        'company_id' => $this->company->id,
        'code' => 'TVA7OFF',
        'name' => 'TVA 7% inactive',
        'type' => 'vat',
        'rate' => 7.0,
        'purchase_tax_account_id' => $this->account4456->id,
        'is_active' => false,
        'is_default' => false,
        'sort_order' => 3,
    ]);

    createDraftExpenseForTest($this, [
        'lines' => [[
            'expense_account_id' => $this->account613->id,
            'label' => 'Ligne',
            'quantity' => '1',
            'unit_price' => '100.000',
            'discount_percent' => '0',
            'tax_rate_id' => $inactive->id,
        ]],
    ]);
})->throws(InvalidArgumentException::class, 'inactif');

// ---------- updateDraft / cancel / deleteDraft ----------

it('updates a draft expense and recalculates totals', function () {
    $service = exService();
    $expense = createDraftExpenseForTest($this);

    $updated = $service->updateDraft($expense->fresh(), [
        'supplier_id' => null,
        'payment_method_id' => $this->methodEsp->id,
        'journal_id' => $this->journalOD->id,
        'expense_date' => '2026-01-20',
        'due_date' => null,
        'reference' => 'JUST-002',
        'description' => 'Loyer révisé',
        'notes' => 'Mise à jour',
        'lines' => [
            [
                'expense_account_id' => $this->account613->id,
                'label' => 'Loyer janvier',
                'quantity' => '1',
                'unit_price' => '1200.000',
                'discount_percent' => '0',
            ],
        ],
    ]);

    expect($updated->reference)->toBe('JUST-002')
        ->and(normalizeDecimal($updated->total))->toBe('1200.000')
        ->and($updated->lines)->toHaveCount(1);
});

it('refuses updating a posted expense', function () {
    $expense = createPostedExpenseForTest($this);

    exService()->updateDraft($expense, [
        'payment_method_id' => null,
        'journal_id' => $this->journalOD->id,
        'expense_date' => '2026-01-15',
        'lines' => [
            [
                'expense_account_id' => $this->account613->id,
                'label' => 'X',
                'quantity' => '1',
                'unit_price' => '1.000',
                'discount_percent' => '0',
            ],
        ],
    ]);
})->throws(InvalidArgumentException::class, 'Seule une dépense en brouillon peut être modifiée.');

it('cancels a draft expense', function () {
    $expense = createDraftExpenseForTest($this);

    $cancelled = exService()->cancel($expense);

    expect($cancelled->status)->toBe(ExpenseStatus::CANCELLED);
});

it('refuses cancelling a posted expense', function () {
    $expense = createPostedExpenseForTest($this);

    exService()->cancel($expense);
})->throws(InvalidArgumentException::class, 'Seule une dépense en brouillon peut être annulée.');

it('deletes a draft expense with its lines', function () {
    $expense = createDraftExpenseForTest($this);

    exService()->deleteDraft($expense->fresh());

    expect(Expense::find($expense->id))->toBeNull()
        ->and(ExpenseLine::where('expense_id', $expense->id)->count())->toBe(0);
});

it('refuses deleting a posted expense', function () {
    $expense = createPostedExpenseForTest($this);

    exService()->deleteDraft($expense);
})->throws(InvalidArgumentException::class, 'Seule une dépense en brouillon peut être supprimée.');

// ---------- Posting: immediate payment mode (A) ----------

it('posts a cash-paid expense with debit charges and credit cash', function () {
    $expense = createPostedExpenseForTest($this);

    expect($expense->status)->toBe(ExpenseStatus::POSTED)
        ->and($expense->posted_at)->not->toBeNull()
        ->and($expense->journal_entry_id)->not->toBeNull();

    $entry = JournalEntry::find($expense->journal_entry_id);
    expect($entry->journal_id)->toBe($this->journalOD->id)
        ->and($entry->status)->toBe(JournalEntryStatus::POSTED)
        ->and($entry->entry_date->toDateString())->toBe('2026-01-15')
        ->and($entry->reference)->toBe($expense->expense_number)
        ->and($entry->isBalanced())->toBeTrue()
        ->and($entry->lines)->toHaveCount(2);

    $debitLine = $entry->lines->firstWhere('account_id', $this->account613->id);
    $creditLine = $entry->lines->firstWhere('account_id', $this->account571->id);

    expect($debitLine)->not->toBeNull()
        ->and(normalizeDecimal($debitLine->debit))->toBe('1000.000')
        ->and(normalizeDecimal($creditLine->credit))->toBe('1000.000');
});

it('credits the bank account for bank transfer expenses', function () {
    $expense = createPostedExpenseForTest($this, ['payment_method_id' => $this->methodVir->id]);

    $entry = JournalEntry::find($expense->journal_entry_id);
    $creditLine = $entry->lines->firstWhere('account_id', $this->account532->id);

    expect($creditLine)->not->toBeNull()
        ->and(normalizeDecimal($creditLine->credit))->toBe('1000.000');
});

it('aggregates VAT recovery and same-account lines in the entry', function () {
    $expense = createPostedExpenseForTest($this, [
        'lines' => [
            [
                'expense_account_id' => $this->account607->id,
                'label' => 'Fournitures A',
                'quantity' => '1',
                'unit_price' => '500.000',
                'discount_percent' => '0',
                'tax_rate_id' => $this->taxRate->id,
            ],
            [
                'expense_account_id' => $this->account607->id,
                'label' => 'Fournitures B',
                'quantity' => '1',
                'unit_price' => '300.000',
                'discount_percent' => '0',
                'tax_rate_id' => $this->taxRate->id,
            ],
        ],
    ]);

    // Total TTC: 800 HT + 152 TVA = 952
    $entry = JournalEntry::find($expense->journal_entry_id);
    expect($entry->lines)->toHaveCount(3)
        ->and(normalizeDecimal($expense->fresh()->total))->toBe('952.000');

    $chargeLine = $entry->lines->firstWhere('account_id', $this->account607->id);
    $taxLine = $entry->lines->firstWhere('account_id', $this->account4456->id);
    $cashLine = $entry->lines->firstWhere('account_id', $this->account571->id);

    expect(normalizeDecimal($chargeLine->debit))->toBe('800.000')
        ->and(normalizeDecimal($taxLine->debit))->toBe('152.000')
        ->and(normalizeDecimal($cashLine->credit))->toBe('952.000');
});

it('refuses posting twice and keeps the entry unique', function () {
    $service = exService();
    $expense = createPostedExpenseForTest($this);
    $entryId = $expense->journal_entry_id;

    $service->post($expense, $this->user->id);
})->throws(InvalidArgumentException::class, 'Seule une dépense en brouillon peut être comptabilisée.');

// ---------- Posting: supplier payable mode (B) ----------

it('credits the supplier account for payable expenses without payment method', function () {
    $expense = createPostedExpenseForTest($this, [
        'payment_method_id' => null,
        'supplier_id' => $this->supplier->id,
    ]);

    $entry = JournalEntry::find($expense->journal_entry_id);
    $creditLine = $entry->lines->firstWhere('account_id', $this->account401->id);

    expect($creditLine)->not->toBeNull()
        ->and(normalizeDecimal($creditLine->credit))->toBe('1000.000')
        ->and($entry->lines)->toHaveCount(2);
});

it('falls back to the default supplier account when the supplier has none', function () {
    $expense = createPostedExpenseForTest($this, [
        'payment_method_id' => null,
        'supplier_id' => $this->supplierSansCompte->id,
    ]);

    $entry = JournalEntry::find($expense->journal_entry_id);
    $creditLine = $entry->lines->firstWhere('account_id', $this->account401->id);

    expect($creditLine)->not->toBeNull()
        ->and(normalizeDecimal($creditLine->credit))->toBe('1000.000');
});

it('prefers immediate payment mode when both method and supplier are set', function () {
    $expense = createPostedExpenseForTest($this, [
        'payment_method_id' => $this->methodEsp->id,
        'supplier_id' => $this->supplier->id,
    ]);

    $entry = JournalEntry::find($expense->journal_entry_id);

    expect($entry->lines->firstWhere('account_id', $this->account571->id))->not->toBeNull()
        ->and($entry->lines->firstWhere('account_id', $this->account401->id))->toBeNull();
});

// ---------- Posting failures ----------

it('fails clearly when no destination account is configured for the cash method', function () {
    CompanyAccountingSetting::where('company_id', $this->company->id)->update(['default_cash_account_id' => null]);

    createDraftExpenseForTest($this);
    exService()->post(Expense::first(), $this->user->id);
})->throws(RuntimeException::class, "Aucun compte de destination n'est configuré pour le mode de règlement");

it('fails when the configured destination account is inactive', function () {
    $this->account571->update(['is_active' => false]);

    createDraftExpenseForTest($this);
    exService()->post(Expense::first(), $this->user->id);
})->throws(RuntimeException::class, "Aucun compte de destination n'est configuré pour le mode de règlement");

it('fails when the supplier has no account and no default exists', function () {
    CompanyAccountingSetting::where('company_id', $this->company->id)->update(['default_supplier_account_id' => null]);

    createDraftExpenseForTest($this, ['payment_method_id' => null, 'supplier_id' => $this->supplierSansCompte->id]);
    exService()->post(Expense::first(), $this->user->id);
})->throws(RuntimeException::class, 'Aucun compte fournisseur configuré');

it('fails clearly without any payment method or supplier', function () {
    createDraftExpenseForTest($this, ['payment_method_id' => null]);
    exService()->post(Expense::first(), $this->user->id);
})->throws(InvalidArgumentException::class, 'Aucun mode de règlement ni fournisseur');

it('fails when the tax rate has no recoverable VAT account', function () {
    TaxRate::create([
        'company_id' => $this->company->id,
        'code' => 'TVA7',
        'name' => 'TVA 7%',
        'type' => 'vat',
        'rate' => 7.0,
        'purchase_tax_account_id' => null,
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 4,
    ]);

    createDraftExpenseForTest($this, [
        'lines' => [[
            'expense_account_id' => $this->account613->id,
            'label' => 'Ligne TVA sans compte',
            'quantity' => '1',
            'unit_price' => '100.000',
            'discount_percent' => '0',
            'tax_rate_id' => TaxRate::where('code', 'TVA7')->first()->id,
        ]],
    ]);

    exService()->post(Expense::first(), $this->user->id);
})->throws(RuntimeException::class, "Aucun compte de TVA récupérable n'est configuré pour le taux");

it('refuses posting into a closed period', function () {
    $expense = createDraftExpenseForTest($this);

    $this->period->update(['is_open' => false, 'is_closed' => true]);

    exService()->post($expense->fresh(), $this->user->id);
})->throws(InvalidArgumentException::class, 'La période comptable est clôturée');

// ---------- General ledger reconciliation ----------

it('reconciles posted expenses with the general ledger', function () {
    createPostedExpenseForTest($this);

    $ledgerCharge = app(GeneralLedgerService::class)->getAccountLedger(
        $this->account613,
        $this->company,
        $this->fiscalYear,
        []
    );
    $ledgerCash = app(GeneralLedgerService::class)->getAccountLedger(
        $this->account571,
        $this->company,
        $this->fiscalYear,
        []
    );

    $chargeDebits = collect($ledgerCharge)->sum(fn ($line) => (float) $line->debit);
    $cashCredits = collect($ledgerCash)->sum(fn ($line) => (float) $line->credit);

    expect(number_format($chargeDebits, 3, '.', ''))->toBe('1000.000')
        ->and(number_format($cashCredits, 3, '.', ''))->toBe('1000.000');
});

// ---------- Livewire: Index ----------

it('renders the expenses index page and filters by search', function () {
    $expense = createDraftExpenseForTest($this);

    Livewire::test(Index::class)
        ->assertOk()
        ->assertSee($expense->expense_number)
        ->set('search', 'INEXISTANT')
        ->assertSee('Aucune dépense trouvée')
        ->set('search', $expense->expense_number)
        ->assertSee($expense->expense_number);
});

it('filters the index by status', function () {
    createPostedExpenseForTest($this);
    $draft = createDraftExpenseForTest($this, ['description' => 'Deuxième dépense']);

    Livewire::test(Index::class)
        ->set('filterStatus', 'draft')
        ->assertOk()
        ->assertSee($draft->expense_number);
});

it('posts an expense from the index page', function () {
    $expense = createDraftExpenseForTest($this);

    Livewire::test(Index::class)
        ->call('post', $expense->id);

    expect($expense->fresh()->status)->toBe(ExpenseStatus::POSTED)
        ->and($expense->fresh()->journal_entry_id)->not->toBeNull();
});

it('cancels an expense from the index page', function () {
    $expense = createDraftExpenseForTest($this);

    Livewire::test(Index::class)
        ->call('cancel', $expense->id);

    expect($expense->fresh()->status)->toBe(ExpenseStatus::CANCELLED);
});

it('deletes a draft expense from the index page', function () {
    $expense = createDraftExpenseForTest($this);

    Livewire::test(Index::class)
        ->call('delete', $expense->id);

    expect(Expense::find($expense->id))->toBeNull();
});

it('forbids deleting a posted expense from the index page', function () {
    $expense = createPostedExpenseForTest($this);

    Livewire::test(Index::class)
        ->call('delete', $expense->id)
        ->assertForbidden();

    expect(Expense::find($expense->id))->not->toBeNull();
});

// ---------- Livewire: Show ----------

it('shows a posted expense with its journal entry', function () {
    $expense = createPostedExpenseForTest($this);

    Livewire::test(Show::class, ['expenseId' => $expense->id])
        ->assertOk()
        ->assertSee($expense->expense_number)
        ->assertSee('Comptabilisée')
        ->assertSee('613000');
});

it('returns 404 for an expense outside the current company', function () {
    $expense = createDraftExpenseForTest($this);

    $other = Company::create(['name' => 'Autre Société', 'currency' => 'TND', 'is_active' => true]);
    $this->user->companies()->attach($other->id, ['role' => 'admin', 'is_active' => true]);
    session(['current_company_id' => $other->id]);

    $this->get(route('expenses.show', $expense->id))->assertNotFound();
});

// ---------- Livewire: Create ----------

it('preselects the default misc journal on the create page', function () {
    Livewire::test(Create::class)
        ->assertOk()
        ->assertSet('journal_id', $this->journalOD->id);
});

it('creates a draft expense through the create page', function () {
    $component = Livewire::test(Create::class)
        ->set('expense_date', '2026-01-15')
        ->set('payment_method_id', (int) $this->methodEsp->id)
        ->set('lines.0.expense_account_id', $this->account613->id)
        ->set('lines.0.label', 'Loyer janvier')
        ->set('lines.0.unit_price', '1500')
        ->call('save', false);

    $expense = Expense::first();
    expect($expense)->not->toBeNull()
        ->and($expense->status)->toBe(ExpenseStatus::DRAFT)
        ->and(normalizeDecimal($expense->total))->toBe('1500.000');

    $component->assertRedirect(route('expenses.show', $expense->id));
});

it('creates and posts an expense through the create page', function () {
    Livewire::test(Create::class)
        ->set('expense_date', '2026-01-15')
        ->set('payment_method_id', (int) $this->methodEsp->id)
        ->set('lines.0.expense_account_id', $this->account613->id)
        ->set('lines.0.label', 'Loyer janvier')
        ->set('lines.0.unit_price', '1500')
        ->call('save', true);

    $expense = Expense::first();
    expect($expense->status)->toBe(ExpenseStatus::POSTED)
        ->and($expense->journal_entry_id)->not->toBeNull()
        ->and(JournalEntry::find($expense->journal_entry_id)->status)->toBe(JournalEntryStatus::POSTED);
});

it('surfaces service errors as flash messages instead of failing', function () {
    // Date outside the current period triggers a clear error, no expense is created.
    Livewire::test(Create::class)
        ->set('expense_date', '2026-03-15')
        ->set('lines.0.expense_account_id', $this->account613->id)
        ->set('lines.0.label', 'Loyer mars')
        ->set('lines.0.unit_price', '100')
        ->call('save', false);

    expect(Expense::count())->toBe(0);
});

// ---------- Livewire: Edit ----------

it('loads and updates a draft on the edit page', function () {
    $service = exService();
    $expense = createDraftExpenseForTest($this);

    Livewire::test(Edit::class, ['expenseId' => $expense->id])
        ->assertOk()
        ->assertSet('expenseNumber', $expense->expense_number)
        ->assertSet('lines.0.unit_price', '1000.000')
        ->set('lines.0.unit_price', '2000')
        ->call('save', false);

    expect(normalizeDecimal($expense->fresh()->total))->toBe('2000.000');
});

it('refuses rendering the edit page for posted expenses', function () {
    $expense = createPostedExpenseForTest($this);

    // The update policy rejects non-draft expenses before the render guard runs.
    Livewire::test(Edit::class, ['expenseId' => $expense->id])
        ->assertForbidden();
});

// ---------- Policies ----------

it('allows members to view but not to create expenses', function () {
    $member = User::factory()->create();
    $member->email_verified_at = now();
    $member->save();
    $member->companies()->attach($this->company->id, ['role' => 'member', 'is_active' => true]);

    $expense = createPostedExpenseForTest($this);

    session(['current_company_id' => $this->company->id]);
    $this->actingAs($member);

    expect($member->can('view', $expense))->toBeTrue();

    Livewire::test(Create::class)
        ->set('lines.0.expense_account_id', $this->account613->id)
        ->set('lines.0.label', 'Interdit')
        ->set('lines.0.unit_price', '10')
        ->call('save', false)
        ->assertForbidden();

    expect(Expense::count())->toBe(1);
});
