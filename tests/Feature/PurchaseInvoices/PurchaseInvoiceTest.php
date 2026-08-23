<?php

use App\Enums\JournalEntryStatus;
use App\Enums\PurchaseInvoiceStatus;
use App\Livewire\PurchaseInvoices\Create;
use App\Livewire\PurchaseInvoices\Index;
use App\Livewire\PurchaseInvoices\Show;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\CompanyAccountingSetting;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\Accounting\GeneralLedgerService;
use App\Services\Accounting\JournalEntryService;
use App\Services\Accounting\TrialBalanceService;
use App\Services\PurchaseInvoicePostingService;
use App\Services\PurchaseInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    $this->journal = Journal::create([
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

    $this->account607 = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => '607000',
        'name' => 'Achats de marchandises',
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

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'code' => 'PRD001',
        'name' => 'Produit Test',
        'type' => 'product',
        'unit' => 'unit',
        'purchase_price' => '25.000',
        'purchase_account_id' => $this->account607->id,
        'tax_rate_id' => $this->taxRate->id,
        'is_active' => true,
        'is_sellable' => false,
        'is_purchasable' => true,
    ]);

    session([
        'current_company_id' => $this->company->id,
        'current_fiscal_year_id' => $this->fiscalYear->id,
        'current_accounting_period_id' => $this->period->id,
    ]);
});

function makePurchaseInvoiceData(Company $company, Supplier $supplier, Product $product, ?TaxRate $taxRate, Journal $journal): array
{
    $fiscalYear = FiscalYear::where('company_id', $company->id)->first();
    $period = AccountingPeriod::where('fiscal_year_id', $fiscalYear->id)->first();

    return [
        'company_id' => $company->id,
        'supplier_id' => $supplier->id,
        'fiscal_year_id' => $fiscalYear->id,
        'accounting_period_id' => $period->id,
        'journal_id' => $journal->id,
        'invoice_date' => '2026-01-15',
        'due_date' => null,
        'currency' => 'TND',
        'payment_terms_days' => 30,
        'notes' => 'Test notes',
        'created_by' => User::factory()->create()->id,
        'lines' => [
            [
                'product_id' => $product->id,
                'description' => 'Produit Test',
                'quantity' => '10.000',
                'unit' => 'unit',
                'unit_price' => '25.000',
                'discount_percent' => '10.000',
                'tax_rate_id' => $taxRate?->id,
            ],
        ],
    ];
}

function createPurchaseDraftForTest($test): PurchaseInvoice
{
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $data = makePurchaseInvoiceData($test->company, $test->supplier, $test->product, $test->taxRate, $test->journal);
    $data['created_by'] = $test->user->id;

    return $service->createDraft($data);
}

// ---------- Draft creation ----------

it('creates a draft supplier invoice', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $data = makePurchaseInvoiceData($this->company, $this->supplier, $this->product, $this->taxRate, $this->journal);
    $data['created_by'] = $this->user->id;

    $invoice = $service->createDraft($data);

    expect($invoice->status)->toBe(PurchaseInvoiceStatus::DRAFT);
    expect($invoice->supplier_id)->toBe($this->supplier->id);
    expect($invoice->company_id)->toBe($this->company->id);
});

it('generates invoice number with ACH prefix and current year', function () {
    $invoice = createPurchaseDraftForTest($this);

    expect($invoice->invoice_number)->toMatch('/^ACH-\d{4}-\d{6}$/');
});

it('generates sequential invoice numbers', function () {
    createPurchaseDraftForTest($this);
    createPurchaseDraftForTest($this);

    $numbers = PurchaseInvoice::orderBy('id')->pluck('invoice_number')->values();
    expect($numbers[0])->toBe('ACH-'.now()->year.'-000001');
    expect($numbers[1])->toBe('ACH-'.now()->year.'-000002');
});

it('scopes invoice numbering per company', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));

    $first = createPurchaseDraftForTest($this);

    $otherCompany = Company::create(['name' => 'Other Co', 'currency' => 'TND', 'is_active' => true]);
    $otherFy = FiscalYear::create([
        'company_id' => $otherCompany->id,
        'name' => 'FY Other',
        'code' => '2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
        'is_closed' => false,
    ]);
    AccountingPeriod::create([
        'fiscal_year_id' => $otherFy->id,
        'name' => '2026-01',
        'code' => '2026-01',
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
        'is_open' => true,
        'is_closed' => false,
    ]);
    $otherJournal = Journal::create([
        'company_id' => $otherCompany->id,
        'fiscal_year_id' => $otherFy->id,
        'code' => 'ACH',
        'name' => 'Achats',
        'type' => 'achats',
        'is_active' => true,
    ]);
    $otherSupplier = Supplier::create([
        'company_id' => $otherCompany->id,
        'code' => 'FO002',
        'name' => 'Other Fournisseur',
        'supplier_type' => 'individual',
        'country' => 'TN',
        'is_active' => true,
    ]);
    $otherProduct = Product::create([
        'company_id' => $otherCompany->id,
        'code' => 'PRD001',
        'name' => 'Produit Other',
        'type' => 'product',
        'unit' => 'unit',
        'purchase_price' => '10.000',
        'is_active' => true,
        'is_purchasable' => true,
    ]);

    $data2 = makePurchaseInvoiceData($otherCompany, $otherSupplier, $otherProduct, null, $otherJournal);
    $data2['created_by'] = $this->user->id;
    $second = $service->createDraft($data2);

    expect($second->invoice_number)->toBe($first->invoice_number);
});

it('calculates line totals with discount and VAT correctly', function () {
    $invoice = createPurchaseDraftForTest($this);

    // 10 × 25.000 = 250 brut ; remise 10% → 225.000 HT ; TVA 19% → 42.750 ; TTC 267.750
    $line = $invoice->lines()->first();

    expect((string) $line->line_subtotal)->toBe('225.000');
    expect((string) $line->discount_amount)->toBe('25.000');
    expect((string) $line->tax_amount)->toBe('42.750');
    expect((string) $line->line_total)->toBe('267.750');

    expect((string) $invoice->fresh()->subtotal)->toBe('225.000');
    expect((string) $invoice->fresh()->discount_total)->toBe('25.000');
    expect((string) $invoice->fresh()->tax_total)->toBe('42.750');
    expect((string) $invoice->fresh()->total)->toBe('267.750');
});

it('handles lines without tax as tax-free', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $data = makePurchaseInvoiceData($this->company, $this->supplier, $this->product, $this->taxRate, $this->journal);
    $data['created_by'] = $this->user->id;
    $data['lines'] = [[
        'product_id' => $this->product->id,
        'description' => 'Sans TVA',
        'quantity' => '2.000',
        'unit' => 'unit',
        'unit_price' => '50.000',
        'discount_percent' => '0.000',
    ]];

    $invoice = $service->createDraft($data);

    expect((string) $invoice->subtotal)->toBe('100.000');
    expect((string) $invoice->tax_total)->toBe('0.000');
    expect((string) $invoice->total)->toBe('100.000');
});

it('aggregates totals across multiple lines', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $data = makePurchaseInvoiceData($this->company, $this->supplier, $this->product, $this->taxRate, $this->journal);
    $data['created_by'] = $this->user->id;
    $data['lines'] = [
        [
            'product_id' => $this->product->id,
            'description' => 'Ligne 1',
            'quantity' => '1.000',
            'unit' => 'unit',
            'unit_price' => '100.000',
            'discount_percent' => '0.000',
            'tax_rate_id' => $this->taxRate->id,
        ],
        [
            'product_id' => $this->product->id,
            'description' => 'Ligne 2',
            'quantity' => '1.000',
            'unit' => 'unit',
            'unit_price' => '200.000',
            'discount_percent' => '0.000',
            'tax_rate_id' => null,
        ],
    ];

    $invoice = $service->createDraft($data);

    expect((string) $invoice->subtotal)->toBe('300.000');
    expect((string) $invoice->tax_total)->toBe('19.000');
    expect((string) $invoice->total)->toBe('319.000');
    expect($invoice->lines)->toHaveCount(2);
});

it('computes due date from payment terms when not provided', function () {
    $invoice = createPurchaseDraftForTest($this);

    expect($invoice->due_date->toDateString())->toBe('2026-02-14');
});

it('keeps explicit due date when provided', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $data = makePurchaseInvoiceData($this->company, $this->supplier, $this->product, $this->taxRate, $this->journal);
    $data['created_by'] = $this->user->id;
    $data['due_date'] = '2026-01-31';

    $invoice = $service->createDraft($data);

    expect($invoice->due_date->toDateString())->toBe('2026-01-31');
});

it('forces currency from company accounting settings', function () {
    CompanyAccountingSetting::create([
        'company_id' => $this->company->id,
        'default_currency' => 'EUR',
    ]);

    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $data = makePurchaseInvoiceData($this->company, $this->supplier, $this->product, $this->taxRate, $this->journal);
    $data['created_by'] = $this->user->id;
    $data['currency'] = 'TND';

    $invoice = $service->createDraft($data);

    expect($invoice->currency)->toBe('EUR');
});

it('stores the supplier own invoice number', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $data = makePurchaseInvoiceData($this->company, $this->supplier, $this->product, $this->taxRate, $this->journal);
    $data['created_by'] = $this->user->id;
    $data['supplier_invoice_number'] = 'FA-2026-98765';

    $invoice = $service->createDraft($data);

    expect($invoice->supplier_invoice_number)->toBe('FA-2026-98765');
});

// ---------- Validation ----------

it('rejects supplier from another company', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $otherCompany = Company::create(['name' => 'Other', 'currency' => 'TND', 'is_active' => true]);
    $otherSupplier = Supplier::create([
        'company_id' => $otherCompany->id,
        'code' => 'FO999',
        'name' => 'Other',
        'supplier_type' => 'individual',
        'country' => 'TN',
        'is_active' => true,
    ]);

    $data = makePurchaseInvoiceData($this->company, $otherSupplier, $this->product, $this->taxRate, $this->journal);

    $service->createDraft($data);
})->throws(InvalidArgumentException::class, "n'appartient pas à cette société");

it('rejects inactive supplier', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $this->supplier->update(['is_active' => false]);

    $data = makePurchaseInvoiceData($this->company, $this->supplier->fresh(), $this->product, $this->taxRate, $this->journal);

    $service->createDraft($data);
})->throws(InvalidArgumentException::class, 'est inactif');

it('rejects product that is not purchasable', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $this->product->update(['is_purchasable' => false]);

    $data = makePurchaseInvoiceData($this->company, $this->supplier, $this->product->fresh(), $this->taxRate, $this->journal);

    $service->createDraft($data);
})->throws(InvalidArgumentException::class, 'achetable');

it('rejects zero quantity', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $data = makePurchaseInvoiceData($this->company, $this->supplier, $this->product, $this->taxRate, $this->journal);
    $data['lines'][0]['quantity'] = '0.000';

    $service->createDraft($data);
})->throws(InvalidArgumentException::class, 'quantité');

it('rejects negative unit price', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $data = makePurchaseInvoiceData($this->company, $this->supplier, $this->product, $this->taxRate, $this->journal);
    $data['lines'][0]['unit_price'] = '-5.000';

    $service->createDraft($data);
})->throws(InvalidArgumentException::class, 'négatif');

it('rejects discount above 100 percent', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $data = makePurchaseInvoiceData($this->company, $this->supplier, $this->product, $this->taxRate, $this->journal);
    $data['lines'][0]['discount_percent'] = '150.000';

    $service->createDraft($data);
})->throws(InvalidArgumentException::class, 'remise');

it('rejects journal that is not a purchases journal', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $ventesJournal = Journal::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => 'VTE',
        'name' => 'Ventes',
        'type' => 'ventes',
        'is_active' => true,
    ]);

    $data = makePurchaseInvoiceData($this->company, $this->supplier, $this->product, $this->taxRate, $ventesJournal);

    $service->createDraft($data);
})->throws(InvalidArgumentException::class, "journal d'achats");

it('rejects closed fiscal year', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $this->fiscalYear->update(['is_closed' => true]);

    $data = makePurchaseInvoiceData($this->company, $this->supplier, $this->product, $this->taxRate, $this->journal);

    $service->createDraft($data);
})->throws(InvalidArgumentException::class, 'exercice comptable est clôturé');

it('rejects closed accounting period', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $this->period->update(['is_closed' => true, 'is_open' => false]);

    $data = makePurchaseInvoiceData($this->company, $this->supplier, $this->product, $this->taxRate, $this->journal);

    $service->createDraft($data);
})->throws(InvalidArgumentException::class, 'période comptable est clôturée');

it('rejects invoice date outside the accounting period', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $data = makePurchaseInvoiceData($this->company, $this->supplier, $this->product, $this->taxRate, $this->journal);
    $data['invoice_date'] = '2026-03-15';

    $service->createDraft($data);
})->throws(InvalidArgumentException::class, 'comprise dans la période');

it('rejects duplicate supplier invoice number for the same supplier', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $data = makePurchaseInvoiceData($this->company, $this->supplier, $this->product, $this->taxRate, $this->journal);
    $data['created_by'] = $this->user->id;
    $data['supplier_invoice_number'] = 'FA-DUP-001';
    $service->createDraft($data);

    $second = makePurchaseInvoiceData($this->company, $this->supplier, $this->product, $this->taxRate, $this->journal);
    $second['created_by'] = $this->user->id;
    $second['supplier_invoice_number'] = 'FA-DUP-001';

    $service->createDraft($second);
})->throws(InvalidArgumentException::class, 'existe déjà');

it('allows same supplier invoice number across different suppliers', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $otherSupplier = Supplier::create([
        'company_id' => $this->company->id,
        'code' => 'FO002',
        'name' => 'Autre Fournisseur',
        'supplier_type' => 'individual',
        'country' => 'TN',
        'is_active' => true,
    ]);

    $data = makePurchaseInvoiceData($this->company, $this->supplier, $this->product, $this->taxRate, $this->journal);
    $data['created_by'] = $this->user->id;
    $data['supplier_invoice_number'] = 'FA-SHARED-001';
    $service->createDraft($data);

    $second = makePurchaseInvoiceData($this->company, $otherSupplier, $this->product, $this->taxRate, $this->journal);
    $second['created_by'] = $this->user->id;
    $second['supplier_invoice_number'] = 'FA-SHARED-001';

    $invoice = $service->createDraft($second);

    expect($invoice->exists())->toBeTrue();
});

// ---------- Draft lifecycle ----------

it('updates a draft and recalculates totals', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createPurchaseDraftForTest($this);

    $data = makePurchaseInvoiceData($this->company, $this->supplier, $this->product, $this->taxRate, $this->journal);
    unset($data['company_id'], $data['fiscal_year_id'], $data['accounting_period_id'], $data['currency'], $data['created_by']);
    $data['invoice_date'] = '2026-01-20';
    $data['supplier_invoice_number'] = 'FA-UPDATED-01';
    $data['lines'] = [[
        'product_id' => $this->product->id,
        'description' => 'Produit Test',
        'quantity' => '12.000',
        'unit' => 'unit',
        'unit_price' => '25.000',
        'discount_percent' => '0.000',
        'tax_rate_id' => $this->taxRate->id,
    ]];

    $updated = $service->updateDraft($invoice, $data);

    expect((string) $updated->subtotal)->toBe('300.000');
    expect((string) $updated->tax_total)->toBe('57.000');
    expect((string) $updated->total)->toBe('357.000');
    expect($updated->due_date->toDateString())->toBe('2026-02-19');
    expect($updated->supplier_invoice_number)->toBe('FA-UPDATED-01');
});

it('rejects updating a posted invoice', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createPurchaseDraftForTest($this);
    $service->post($invoice, $this->user->id);

    $data = makePurchaseInvoiceData($this->company, $this->supplier, $this->product, $this->taxRate, $this->journal);
    unset($data['company_id'], $data['fiscal_year_id'], $data['accounting_period_id'], $data['currency'], $data['created_by']);

    $service->updateDraft($invoice, $data);
})->throws(InvalidArgumentException::class, 'brouillon peut être modifiée');

it('cancels a draft invoice', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createPurchaseDraftForTest($this);

    $cancelled = $service->cancel($invoice);

    expect($cancelled->status)->toBe(PurchaseInvoiceStatus::CANCELLED);
});

it('rejects cancelling a posted invoice', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createPurchaseDraftForTest($this);
    $service->post($invoice, $this->user->id);

    $service->cancel($invoice->fresh());
})->throws(InvalidArgumentException::class, 'brouillon peut être annulée');

it('deletes a draft invoice with its lines', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createPurchaseDraftForTest($this);

    $service->deleteDraft($invoice);

    expect(PurchaseInvoice::count())->toBe(0);
    expect(DB::table('purchase_invoice_lines')->count())->toBe(0);
});

it('rejects deleting a posted invoice', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createPurchaseDraftForTest($this);
    $service->post($invoice, $this->user->id);

    $service->deleteDraft($invoice->fresh());
})->throws(InvalidArgumentException::class, 'brouillon peut être supprimée');

it('does not reuse the number of a cancelled invoice', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $first = createPurchaseDraftForTest($this);
    $service->cancel($first);

    $second = createPurchaseDraftForTest($this);

    expect($second->invoice_number)->toBe('ACH-'.now()->year.'-000002');
});

// ---------- Posting ----------

it('posts a draft into a balanced journal entry', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createPurchaseDraftForTest($this);

    $posted = $service->post($invoice, $this->user->id);

    expect($posted->status)->toBe(PurchaseInvoiceStatus::POSTED);
    expect($posted->posted_at)->not->toBeNull();
    expect($posted->journal_entry_id)->not->toBeNull();

    $entry = $posted->journalEntry;

    expect($entry->status)->toBe(JournalEntryStatus::POSTED);
    expect($entry->reference)->toBe($invoice->invoice_number);
    expect($entry->entry_date->toDateString())->toBe('2026-01-15');
    expect($entry->journal_id)->toBe($this->journal->id);

    $totalDebit = '0';
    $totalCredit = '0';
    foreach ($entry->lines()->get() as $line) {
        $totalDebit = bcadd($totalDebit, (string) $line->debit, 3);
        $totalCredit = bcadd($totalCredit, (string) $line->credit, 3);
    }

    expect($totalDebit)->toBe('267.750');
    expect($totalCredit)->toBe('267.750');
});

it('credits the supplier account for the full TTC amount', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createPurchaseDraftForTest($this);
    $service->post($invoice, $this->user->id);

    $creditLines = $invoice->fresh()->journalEntry->lines()
        ->where('credit', '>', '0')->get();

    expect($creditLines)->toHaveCount(1);
    expect($creditLines[0]->account_id)->toBe($this->account401->id);
    expect((string) $creditLines[0]->credit)->toBe('267.750');
    expect((string) $creditLines[0]->debit)->toBe('0.000');
});

it('debits the purchase account for the net amount', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createPurchaseDraftForTest($this);
    $service->post($invoice, $this->user->id);

    $debitLine = $invoice->fresh()->journalEntry->lines()
        ->where('account_id', $this->account607->id)->first();

    expect($debitLine)->not->toBeNull();
    expect((string) $debitLine->debit)->toBe('225.000');
});

it('debits the recoverable VAT account', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createPurchaseDraftForTest($this);
    $service->post($invoice, $this->user->id);

    $vatLine = $invoice->fresh()->journalEntry->lines()
        ->where('account_id', $this->account4456->id)->first();

    expect($vatLine)->not->toBeNull();
    expect((string) $vatLine->debit)->toBe('42.750');
});

it('groups debit lines by account across multiple lines', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $data = makePurchaseInvoiceData($this->company, $this->supplier, $this->product, $this->taxRate, $this->journal);
    $data['created_by'] = $this->user->id;
    $data['lines'] = [
        [
            'product_id' => $this->product->id,
            'description' => 'Avec TVA',
            'quantity' => '2.000',
            'unit' => 'unit',
            'unit_price' => '50.000',
            'discount_percent' => '0.000',
            'tax_rate_id' => $this->taxRate->id,
        ],
        [
            'product_id' => $this->product->id,
            'description' => 'Sans TVA',
            'quantity' => '1.000',
            'unit' => 'unit',
            'unit_price' => '200.000',
            'discount_percent' => '0.000',
            'tax_rate_id' => null,
        ],
    ];
    $invoice = $service->createDraft($data);
    $service->post($invoice, $this->user->id);

    $lines = $invoice->fresh()->journalEntry->lines()->get();

    // 300 achats (regroupés) + 19 TVA = 2 débits ; 319 crédit fournisseur
    expect($lines)->toHaveCount(3);

    $purchaseLine = $lines->firstWhere('account_id', $this->account607->id);
    expect((string) $purchaseLine->debit)->toBe('300.000');

    $vatLine = $lines->firstWhere('account_id', $this->account4456->id);
    expect((string) $vatLine->debit)->toBe('19.000');

    $supplierLine = $lines->firstWhere('account_id', $this->account401->id);
    expect((string) $supplierLine->credit)->toBe('319.000');
});

it('falls back to the default supplier account from settings', function () {
    CompanyAccountingSetting::create([
        'company_id' => $this->company->id,
        'default_supplier_account_id' => $this->account401->id,
    ]);
    $bareSupplier = Supplier::create([
        'company_id' => $this->company->id,
        'code' => 'FO003',
        'name' => 'Sans Compte',
        'supplier_type' => 'individual',
        'country' => 'TN',
        'is_active' => true,
    ]);

    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $data = makePurchaseInvoiceData($this->company, $bareSupplier, $this->product, $this->taxRate, $this->journal);
    $data['created_by'] = $this->user->id;
    $invoice = $service->createDraft($data);
    $service->post($invoice, $this->user->id);

    $creditLines = $invoice->fresh()->journalEntry->lines()
        ->where('credit', '>', '0')->get();

    expect($creditLines)->toHaveCount(1);
    expect($creditLines[0]->account_id)->toBe($this->account401->id);
});

it('falls back to the default purchase account from settings', function () {
    CompanyAccountingSetting::create([
        'company_id' => $this->company->id,
        'default_purchase_account_id' => $this->account607->id,
    ]);
    $bareProduct = Product::create([
        'company_id' => $this->company->id,
        'code' => 'PRD002',
        'name' => 'Produit Sans Compte',
        'type' => 'product',
        'unit' => 'unit',
        'purchase_price' => '25.000',
        'is_active' => true,
        'is_purchasable' => true,
    ]);

    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $data = makePurchaseInvoiceData($this->company, $this->supplier, $bareProduct, $this->taxRate, $this->journal);
    $data['created_by'] = $this->user->id;
    $data['lines'][0]['discount_percent'] = '0.000';
    $invoice = $service->createDraft($data);
    $service->post($invoice, $this->user->id);

    $purchaseLine = $invoice->fresh()->journalEntry->lines()
        ->where('account_id', $this->account607->id)->first();

    expect($purchaseLine)->not->toBeNull();
    expect((string) $purchaseLine->debit)->toBe('250.000');
});

it('fails when no supplier account can be resolved', function () {
    Supplier::query()->where('company_id', $this->company->id)->update(['account_id' => null]);

    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createPurchaseDraftForTest($this);

    $service->post($invoice, $this->user->id);
})->throws(InvalidArgumentException::class, 'Aucun compte fournisseur configuré');

it('fails when the tax rate has no recoverable VAT account', function () {
    $this->taxRate->update(['purchase_tax_account_id' => null]);

    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createPurchaseDraftForTest($this);

    $service->post($invoice, $this->user->id);
})->throws(InvalidArgumentException::class, "Compte d'achat (TVA)");

it('fails when no purchase account can be resolved', function () {
    Product::query()->where('company_id', $this->company->id)->update(['purchase_account_id' => null]);

    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createPurchaseDraftForTest($this);

    $service->post($invoice, $this->user->id);
})->throws(InvalidArgumentException::class, "Aucun compte d'achat configuré");

it('rejects posting an already posted invoice', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createPurchaseDraftForTest($this);
    $service->post($invoice, $this->user->id);

    $service->post($invoice->fresh(), $this->user->id);
})->throws(InvalidArgumentException::class, 'brouillon peut être comptabilisée');

it('rejects posting a cancelled invoice', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createPurchaseDraftForTest($this);
    $service->cancel($invoice);

    $service->post($invoice->fresh(), $this->user->id);
})->throws(InvalidArgumentException::class, 'brouillon peut être comptabilisée');

it('rejects posting an invoice without lines', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createPurchaseDraftForTest($this);

    DB::table('purchase_invoice_lines')
        ->where('purchase_invoice_id', $invoice->id)
        ->delete();

    $service->post($invoice->fresh(), $this->user->id);
})->throws(InvalidArgumentException::class, 'au moins une ligne');

it('revalidates the journal type at posting time', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createPurchaseDraftForTest($this);

    DB::table('journals')->where('id', $this->journal->id)->update(['type' => 'ventes']);

    $service->post($invoice, $this->user->id);
})->throws(InvalidArgumentException::class, "n'est pas un journal d'achats");

it('rejects posting when the period was closed after creation', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createPurchaseDraftForTest($this);

    $this->period->update(['is_closed' => true, 'is_open' => false]);

    $service->post($invoice, $this->user->id);
})->throws(InvalidArgumentException::class, 'Impossible de comptabiliser');

it('generates a sequential journal entry number on posting', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createPurchaseDraftForTest($this);
    $service->post($invoice, $this->user->id);

    $entry = $invoice->fresh()->journalEntry;

    expect($entry->entry_number)->toMatch('/^ACH-2026-\d{6}$/');
});

// ---------- Livewire ----------

it('renders the index page', function () {
    $this->actingAs($this->user);
    $invoice = createPurchaseDraftForTest($this);

    Livewire::test(Index::class)
        ->assertSee($invoice->invoice_number)
        ->assertSee('Fournisseur Test');
});

it('filters invoices by status on the index page', function () {
    $this->actingAs($this->user);
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $draft = createPurchaseDraftForTest($this);
    $cancelled = createPurchaseDraftForTest($this);
    $service->cancel($cancelled);

    Livewire::test(Index::class, ['filterStatus' => 'draft'])
        ->assertSee($draft->invoice_number)
        ->assertDontSee($cancelled->invoice_number);

    Livewire::test(Index::class, ['filterStatus' => 'cancelled'])
        ->assertSee($cancelled->invoice_number)
        ->assertDontSee($draft->invoice_number);
});

it('searches invoices by internal number on the index page', function () {
    $this->actingAs($this->user);
    $invoice = createPurchaseDraftForTest($this);

    Livewire::test(Index::class, ['search' => '000001'])
        ->assertSee($invoice->invoice_number);

    Livewire::test(Index::class, ['search' => 'INEXISTANT'])
        ->assertDontSee($invoice->invoice_number);
});

it('searches invoices by supplier invoice number on the index page', function () {
    $this->actingAs($this->user);
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $data = makePurchaseInvoiceData($this->company, $this->supplier, $this->product, $this->taxRate, $this->journal);
    $data['created_by'] = $this->user->id;
    $data['supplier_invoice_number'] = 'FA-2026-98765';
    $service->createDraft($data);

    Livewire::test(Index::class, ['search' => 'FA-2026-98765'])
        ->assertSee('FA-2026-98765');
});

it('hides invoices from other companies on the index page', function () {
    $this->actingAs($this->user);
    $invoice = createPurchaseDraftForTest($this);

    $otherCompany = Company::create(['name' => 'Autre Société', 'currency' => 'TND', 'is_active' => true]);
    $otherCompany->users()->attach($this->user, ['role' => 'admin', 'is_active' => true]);

    session(['current_company_id' => $otherCompany->id]);

    Livewire::test(Index::class)
        ->assertDontSee($invoice->invoice_number);
});

it('creates a draft from the create page', function () {
    $this->actingAs($this->user);
    Livewire::test(Create::class)
        ->set('supplier_id', $this->supplier->id)
        ->set('invoice_date', '2026-01-15')
        ->set('journal_id', $this->journal->id)
        ->set('lines.0.product_id', $this->product->id)
        ->set('lines.0.quantity', '10')
        ->set('lines.0.unit_price', '25')
        ->set('lines.0.discount_percent', '10')
        ->set('lines.0.tax_rate_id', $this->taxRate->id)
        ->call('store');

    expect(PurchaseInvoice::count())->toBe(1);

    $invoice = PurchaseInvoice::first();
    expect($invoice->status)->toBe(PurchaseInvoiceStatus::DRAFT);
    expect((string) $invoice->total)->toBe('267.750');
});

it('creates and posts from the create page', function () {
    $this->actingAs($this->user);
    Livewire::test(Create::class)
        ->set('supplier_id', $this->supplier->id)
        ->set('invoice_date', '2026-01-15')
        ->set('journal_id', $this->journal->id)
        ->set('saveAndPost', true)
        ->set('lines.0.product_id', $this->product->id)
        ->set('lines.0.quantity', '10')
        ->set('lines.0.unit_price', '25')
        ->set('lines.0.discount_percent', '10')
        ->call('store');

    expect(PurchaseInvoice::count())->toBe(1);
    expect(PurchaseInvoice::first()->status)->toBe(PurchaseInvoiceStatus::POSTED);
    expect(DB::table('journal_entries')->count())->toBe(1);
});

it('posts an invoice from the index page', function () {
    $this->actingAs($this->user);
    $invoice = createPurchaseDraftForTest($this);

    Livewire::test(Index::class)->call('post', $invoice);

    expect($invoice->fresh()->status)->toBe(PurchaseInvoiceStatus::POSTED);
});

it('cancels an invoice from the index page', function () {
    $this->actingAs($this->user);
    $invoice = createPurchaseDraftForTest($this);

    Livewire::test(Index::class)->call('cancel', $invoice);

    expect($invoice->fresh()->status)->toBe(PurchaseInvoiceStatus::CANCELLED);
});

it('deletes a draft from the index page', function () {
    $this->actingAs($this->user);
    $invoice = createPurchaseDraftForTest($this);

    Livewire::test(Index::class)->call('delete', $invoice);

    expect(PurchaseInvoice::count())->toBe(0);
});

it('renders the show page', function () {
    $this->actingAs($this->user);
    $invoice = createPurchaseDraftForTest($this);

    Livewire::test(Show::class, ['purchaseInvoiceId' => $invoice->id])
        ->assertSee($invoice->invoice_number)
        ->assertSee('Fournisseur Test');
});

it('forbids opening the edit page of a posted invoice', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createPurchaseDraftForTest($this);
    $service->post($invoice, $this->user->id);

    $this->actingAs($this->user)
        ->get(route('purchase-invoices.edit', ['purchaseInvoiceId' => $invoice->id]))
        ->assertStatus(403);
});

it('returns 404 when showing an invoice from another company', function () {
    $invoice = createPurchaseDraftForTest($this);

    $otherCompany = Company::create(['name' => 'Autre Société', 'currency' => 'TND', 'is_active' => true]);
    $otherCompany->users()->attach($this->user, ['role' => 'admin', 'is_active' => true]);

    session(['current_company_id' => $otherCompany->id]);

    $this->actingAs($this->user)
        ->get(route('purchase-invoices.show', ['purchaseInvoiceId' => $invoice->id]))
        ->assertStatus(404);
});

// ---------- General ledger / Trial balance ----------

it('keeps the trial balance balanced after posting supplier invoices', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createPurchaseDraftForTest($this);
    $service->post($invoice, $this->user->id);

    $totals = app(TrialBalanceService::class)->getGrandTotals($this->company, $this->fiscalYear);

    expect((string) $totals['total_debit'])->toBe('267.750');
    expect((string) $totals['total_credit'])->toBe('267.750');
});

it('shows supplier invoice movements in the general ledger', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createPurchaseDraftForTest($this);
    $service->post($invoice, $this->user->id);

    $ledger607 = app(GeneralLedgerService::class)->getAccountLedger(
        $this->account607,
        $this->company,
        $this->fiscalYear,
        []
    );
    expect($ledger607)->toHaveCount(1);
    expect((string) $ledger607[0]->debit)->toBe('225.000');
    expect((string) $ledger607[0]->credit)->toBe('0.000');
    expect($ledger607[0]->line_description)->toContain($invoice->invoice_number);

    $ledger401 = app(GeneralLedgerService::class)->getAccountLedger(
        $this->account401,
        $this->company,
        $this->fiscalYear,
        []
    );
    expect($ledger401)->toHaveCount(1);
    expect((string) $ledger401[0]->credit)->toBe('267.750');
});
