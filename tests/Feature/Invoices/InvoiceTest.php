<?php

use App\Enums\InvoiceStatus;
use App\Enums\JournalEntryStatus;
use App\Enums\QuoteStatus;
use App\Livewire\Invoices\Create;
use App\Livewire\Invoices\Edit;
use App\Livewire\Invoices\Index;
use App\Livewire\Invoices\Show;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\CompanyAccountingSetting;
use App\Models\Customer;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Product;
use App\Models\Quote;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\Accounting\JournalEntryService;
use App\Services\InvoiceService;
use App\Services\QuoteService;
use App\Services\SalesInvoicePostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
        'code' => 'VTE',
        'name' => 'Ventes',
        'type' => 'ventes',
        'is_active' => true,
    ]);

    $this->account411 = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => '411000',
        'name' => 'Clients',
        'account_type' => 'asset',
        'is_active' => true,
    ]);

    $this->account707 = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => '707000',
        'name' => 'Ventes de marchandises',
        'account_type' => 'revenue',
        'is_active' => true,
    ]);

    $this->account4457 = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => '445700',
        'name' => 'TVA collectée',
        'account_type' => 'liability',
        'is_active' => true,
    ]);

    $this->customer = Customer::create([
        'company_id' => $this->company->id,
        'code' => 'CL001',
        'name' => 'Client Test',
        'customer_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 30,
        'account_id' => $this->account411->id,
        'is_active' => true,
    ]);

    $this->taxRate = TaxRate::create([
        'company_id' => $this->company->id,
        'code' => 'TVA19',
        'name' => 'TVA 19%',
        'type' => 'vat',
        'rate' => 19.0,
        'sales_tax_account_id' => $this->account4457->id,
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
        'sale_price' => '25.000',
        'sales_account_id' => $this->account707->id,
        'tax_rate_id' => $this->taxRate->id,
        'is_active' => true,
        'is_sellable' => true,
        'is_purchasable' => true,
    ]);

    session([
        'current_company_id' => $this->company->id,
        'current_fiscal_year_id' => $this->fiscalYear->id,
        'current_accounting_period_id' => $this->period->id,
    ]);
});

function makeInvoiceData(Company $company, Customer $customer, Product $product, ?TaxRate $taxRate, Journal $journal): array
{
    $fiscalYear = FiscalYear::where('company_id', $company->id)->first();
    $period = AccountingPeriod::where('fiscal_year_id', $fiscalYear->id)->first();

    return [
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'fiscal_year_id' => $fiscalYear->id,
        'accounting_period_id' => $period->id,
        'journal_id' => $journal->id,
        'invoice_date' => '2026-01-15',
        'due_date' => '2026-02-14',
        'currency' => 'TND',
        'payment_terms_days' => 30,
        'notes' => 'Test notes',
        'terms' => 'Test terms',
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

function createDraftForTest($test): Invoice
{
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $data = makeInvoiceData($test->company, $test->customer, $test->product, $test->taxRate, $test->journal);
    $data['created_by'] = $test->user->id;

    $invoice = $service->createDraft($data);

    // Set accounting context required for posting (normally resolved from session).
    $invoice->update([
        'fiscal_year_id' => $test->fiscalYear->id,
        'accounting_period_id' => $test->period->id,
    ]);

    return $invoice->fresh();
}

// ---------- Draft creation ----------

it('creates a draft invoice', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $data = makeInvoiceData($this->company, $this->customer, $this->product, $this->taxRate, $this->journal);
    $data['created_by'] = $this->user->id;

    $invoice = $service->createDraft($data);

    expect($invoice->status)->toBe(InvoiceStatus::DRAFT);
    expect($invoice->customer_id)->toBe($this->customer->id);
    expect($invoice->company_id)->toBe($this->company->id);
});

it('generates invoice number with FAC prefix and current year', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $data = makeInvoiceData($this->company, $this->customer, $this->product, $this->taxRate, $this->journal);
    $data['created_by'] = $this->user->id;

    $invoice = $service->createDraft($data);

    expect($invoice->invoice_number)->toMatch('/^FAC-\d{4}-\d{6}$/');
});

it('generates sequential invoice numbers', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));

    foreach ([1, 2] as $i) {
        $data = makeInvoiceData($this->company, $this->customer, $this->product, $this->taxRate, $this->journal);
        $data['created_by'] = $this->user->id;
        $service->createDraft($data);
    }

    $numbers = Invoice::orderBy('id')->pluck('invoice_number')->values();
    expect($numbers[1])->not->toBe($numbers[0]);
});

it('scopes invoice numbering per company', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));

    $otherCompany = Company::create(['name' => 'Other Co', 'currency' => 'TND', 'is_active' => true]);

    $data = makeInvoiceData($this->company, $this->customer, $this->product, $this->taxRate, $this->journal);
    $data['created_by'] = $this->user->id;
    $first = $service->createDraft($data);

    $otherCustomer = Customer::create([
        'company_id' => $otherCompany->id,
        'code' => 'CL002',
        'name' => 'Other Client',
        'customer_type' => 'individual',
        'country' => 'TN',
        'is_active' => true,
    ]);
    $otherFy = FiscalYear::create([
        'company_id' => $otherCompany->id,
        'name' => 'FY Other',
        'code' => '2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
        'is_closed' => false,
    ]);
    $otherJournal = Journal::create([
        'company_id' => $otherCompany->id,
        'fiscal_year_id' => $otherFy->id,
        'code' => 'VTE',
        'name' => 'Ventes',
        'type' => 'ventes',
        'is_active' => true,
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
    $otherProduct = Product::create([
        'company_id' => $otherCompany->id,
        'code' => 'PRD001',
        'name' => 'Produit Other',
        'type' => 'product',
        'unit' => 'unit',
        'sale_price' => '10.000',
        'is_active' => true,
        'is_sellable' => true,
    ]);

    $data2 = makeInvoiceData($otherCompany, $otherCustomer, $otherProduct, null, $otherJournal);
    $data2['created_by'] = $this->user->id;
    $data2['lines'] = [[
        'product_id' => $otherProduct->id,
        'description' => 'x',
        'quantity' => '1.000',
        'unit' => 'unit',
        'unit_price' => '10.000',
        'discount_percent' => '0.000',
    ]];
    unset($data2['lines'][0]['tax_rate_id']);
    $second = $service->createDraft($data2);

    expect($second->invoice_number)->toBe($first->invoice_number);
});

it('calculates line totals with discount and VAT correctly', function () {
    $invoice = createDraftForTest($this);

    // 10 × 25.000 = 250 gross; 10% discount → 22.500 HT; 19% TVA → 4.275; TTC 26.775
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

it('handles lines without tax rate as tax-free', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $data = makeInvoiceData($this->company, $this->customer, $this->product, $this->taxRate, $this->journal);
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
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $data = makeInvoiceData($this->company, $this->customer, $this->product, $this->taxRate, $this->journal);
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

// ---------- Validation ----------

it('rejects customer from another company', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $otherCompany = Company::create(['name' => 'Other', 'currency' => 'TND', 'is_active' => true]);
    $otherCustomer = Customer::create([
        'company_id' => $otherCompany->id,
        'code' => 'CL999',
        'name' => 'Other',
        'customer_type' => 'individual',
        'country' => 'TN',
        'is_active' => true,
    ]);

    $data = makeInvoiceData($this->company, $otherCustomer, $this->product, $this->taxRate, $this->journal);

    $service->createDraft($data);
})->throws(InvalidArgumentException::class, "n'appartient pas à cette société");

it('rejects inactive customer', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $this->customer->update(['is_active' => false]);

    $data = makeInvoiceData($this->company, $this->customer->fresh(), $this->product, $this->taxRate, $this->journal);

    $service->createDraft($data);
})->throws(InvalidArgumentException::class, 'est inactif');

it('rejects product that is not sellable', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $this->product->update(['is_sellable' => false]);

    $data = makeInvoiceData($this->company, $this->customer, $this->product->fresh(), $this->taxRate, $this->journal);

    $service->createDraft($data);
})->throws(InvalidArgumentException::class);

it('rejects zero quantity', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $data = makeInvoiceData($this->company, $this->customer, $this->product, $this->taxRate, $this->journal);
    $data['lines'][0]['quantity'] = '0.000';

    $service->createDraft($data);
})->throws(InvalidArgumentException::class, 'quantité');

it('rejects negative unit price', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $data = makeInvoiceData($this->company, $this->customer, $this->product, $this->taxRate, $this->journal);
    $data['lines'][0]['unit_price'] = '-5.000';

    $service->createDraft($data);
})->throws(InvalidArgumentException::class, 'négatif');

it('rejects discount above 100 percent', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $data = makeInvoiceData($this->company, $this->customer, $this->product, $this->taxRate, $this->journal);
    $data['lines'][0]['discount_percent'] = '150.000';

    $service->createDraft($data);
})->throws(InvalidArgumentException::class, 'remise');

it('rejects inactive journal', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $this->journal->update(['is_active' => false]);

    $data = makeInvoiceData($this->company, $this->customer, $this->product, $this->taxRate, $this->journal->fresh());

    $service->createDraft($data);
})->throws(InvalidArgumentException::class);

it('rejects closed fiscal year', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $this->fiscalYear->update(['is_closed' => true]);

    $data = makeInvoiceData($this->company, $this->customer, $this->product, $this->taxRate, $this->journal);
    $data['fiscal_year_id'] = $this->fiscalYear->id;

    $service->createDraft($data);
})->throws(InvalidArgumentException::class, 'exercice comptable est clôturé');

it('rejects closed accounting period', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $this->period->update(['is_closed' => true, 'is_open' => false]);

    $data = makeInvoiceData($this->company, $this->customer, $this->product, $this->taxRate, $this->journal);
    $data['fiscal_year_id'] = $this->fiscalYear->id;
    $data['accounting_period_id'] = $this->period->id;

    $service->createDraft($data);
})->throws(InvalidArgumentException::class, 'période comptable est clôturée');

it('rejects invoice date outside the accounting period', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $data = makeInvoiceData($this->company, $this->customer, $this->product, $this->taxRate, $this->journal);
    $data['fiscal_year_id'] = $this->fiscalYear->id;
    $data['accounting_period_id'] = $this->period->id;
    $data['invoice_date'] = '2026-03-15';

    $service->createDraft($data);
})->throws(InvalidArgumentException::class, 'comprise dans la période');

// ---------- Update / cancel / delete ----------

it('updates a draft invoice and recalculates totals', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createDraftForTest($this);

    $updated = $service->updateDraft($invoice, [
        'customer_id' => $this->customer->id,
        'journal_id' => $this->journal->id,
        'invoice_date' => '2026-01-20',
        'currency' => 'TND',
        'payment_terms_days' => 60,
        'notes' => 'Updated',
        'lines' => [
            [
                'product_id' => $this->product->id,
                'description' => 'Modifié',
                'quantity' => '3.000',
                'unit' => 'unit',
                'unit_price' => '100.000',
                'discount_percent' => '0.000',
                'tax_rate_id' => $this->taxRate->id,
            ],
        ],
    ]);

    expect($updated->lines()->count())->toBe(1);
    expect((string) $updated->total)->toBe('357.000');
    expect($updated->notes)->toBe('Updated');
});

it('rejects updating a posted invoice', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createDraftForTest($this);
    $service->post($invoice, $this->user->id);

    $service->updateDraft($invoice->fresh(), [
        'customer_id' => $this->customer->id,
        'journal_id' => $this->journal->id,
        'invoice_date' => '2026-01-20',
        'currency' => 'TND',
        'payment_terms_days' => 30,
        'lines' => [],
    ]);
})->throws(InvalidArgumentException::class, 'brouillon');

it('cancels a draft invoice', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createDraftForTest($this);

    $cancelled = $service->cancel($invoice);

    expect($cancelled->status)->toBe(InvoiceStatus::CANCELLED);
});

it('rejects cancelling a posted invoice', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createDraftForTest($this);
    $service->post($invoice, $this->user->id);

    $service->cancel($invoice->fresh());
})->throws(InvalidArgumentException::class, 'brouillon');

it('deletes a draft invoice with its lines', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createDraftForTest($this);
    $invoiceId = $invoice->id;

    $service->deleteDraft($invoice);

    expect(Invoice::find($invoiceId))->toBeNull();
    expect(DB::table('invoice_lines')->where('invoice_id', $invoiceId)->count())->toBe(0);
});

it('rejects deleting a posted invoice', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createDraftForTest($this);
    $service->post($invoice, $this->user->id);

    $service->deleteDraft($invoice->fresh());
})->throws(InvalidArgumentException::class, 'brouillon');

// ---------- Posting ----------

it('posts an invoice and creates a balanced journal entry', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createDraftForTest($this);

    $posted = $service->post($invoice, $this->user->id);

    expect($posted->status)->toBe(InvoiceStatus::POSTED);
    expect($posted->posted_at)->not->toBeNull();
    expect($posted->journal_entry_id)->not->toBeNull();

    $entry = $posted->journalEntry;
    expect($entry->status)->toBe(JournalEntryStatus::POSTED);
    expect($entry->reference)->toBe($posted->invoice_number);
    expect($entry->company_id)->toBe($this->company->id);
    expect($entry->totalDebit())->toBe($entry->totalCredit());
    expect($entry->totalDebit())->toBe('267.750');
    expect($entry->totalCredit())->toBe('267.750');
});

it('debits customer account by TTC total', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createDraftForTest($this);
    $service->post($invoice, $this->user->id);

    $debitLines = $invoice->fresh()->journalEntry->lines()
        ->where('debit', '>', 0)
        ->get();

    expect($debitLines)->toHaveCount(1);
    expect($debitLines->first()->account_id)->toBe($this->account411->id);
    expect((string) $debitLines->first()->debit)->toBe('267.750');
});

it('credits sales accounts aggregated per account', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createDraftForTest($this);
    $service->post($invoice, $this->user->id);

    $credit707 = $invoice->fresh()->journalEntry->lines()
        ->where('account_id', $this->account707->id)
        ->first();

    expect($credit707)->not->toBeNull();
    expect((string) $credit707->credit)->toBe('225.000');
});

it('credits VAT account using the tax rate sales tax account', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createDraftForTest($this);
    $service->post($invoice, $this->user->id);

    $credit4457 = $invoice->fresh()->journalEntry->lines()
        ->where('account_id', $this->account4457->id)
        ->first();

    expect($credit4457)->not->toBeNull();
    expect((string) $credit4457->credit)->toBe('42.750');
});

it('falls back to default customer account when customer has none', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $this->customer->update(['account_id' => null]);

    CompanyAccountingSetting::create([
        'company_id' => $this->company->id,
        'default_customer_account_id' => $this->account411->id,
    ]);

    $invoice = createDraftForTest($this);

    $service->post($invoice, $this->user->id);

    $debitLine = $invoice->fresh()->journalEntry->lines()->where('debit', '>', 0)->first();
    expect($debitLine->account_id)->toBe($this->account411->id);
});

it('fails posting when no receivable account is available', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $this->customer->update(['account_id' => null]);

    $invoice = createDraftForTest($this);

    $service->post($invoice, $this->user->id);
})->throws(InvalidArgumentException::class, 'Aucun compte client configuré');

it('fails posting when tax rate has no sales tax account', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $this->taxRate->update(['sales_tax_account_id' => null]);

    $invoice = createDraftForTest($this);

    $service->post($invoice, $this->user->id);
})->throws(InvalidArgumentException::class, 'Compte de vente (TVA)');

it('rejects posting an already posted invoice', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createDraftForTest($this);
    $service->post($invoice, $this->user->id);

    $service->post($invoice->fresh(), $this->user->id);
})->throws(InvalidArgumentException::class, 'brouillon');

it('rejects posting an empty invoice', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createDraftForTest($this);
    $invoice->lines()->delete();

    $service->post($invoice->fresh(), $this->user->id);
})->throws(InvalidArgumentException::class, 'au moins une ligne');

it('uses journal code in generated entry number', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createDraftForTest($this);
    $service->post($invoice, $this->user->id);

    $entry = $invoice->fresh()->journalEntry;

    expect($entry->entry_number)->toStartWith('VTE-2026-');
    expect($entry->entry_number)->toMatch('/^VTE-2026-\d{6}$/');
});

// ---------- Quote conversion ----------

function createAcceptedQuote($test): Quote
{
    $quoteService = new QuoteService;
    $data = [
        'company_id' => $test->company->id,
        'customer_id' => $test->customer->id,
        'quote_date' => '2026-01-10',
        'valid_until' => '2026-02-10',
        'currency' => 'TND',
        'payment_terms_days' => 30,
        'notes' => 'Devis notes',
        'terms' => 'Devis terms',
        'created_by' => $test->user->id,
        'lines' => [
            [
                'product_id' => $test->product->id,
                'description' => 'Produit Test',
                'quantity' => '10.000',
                'unit' => 'unit',
                'unit_price' => '25.000',
                'discount_percent' => '10.000',
                'tax_rate_id' => $test->taxRate->id,
            ],
        ],
    ];
    $quote = $quoteService->createDraft($data);
    $quoteService->send($quote);
    $quote = $quote->fresh();
    $quoteService->accept($quote);

    return $quote->fresh();
}

/**
 * Accounting period covering the current date, since createFromQuote()
 * stamps the invoice with today's date (production behavior).
 */
function currentPeriodContainingToday($test): AccountingPeriod
{
    return AccountingPeriod::create([
        'fiscal_year_id' => $test->fiscalYear->id,
        'name' => now()->format('Y-m'),
        'code' => now()->format('Y-m'),
        'start_date' => now()->startOfMonth(),
        'end_date' => now()->endOfMonth(),
        'is_open' => true,
        'is_closed' => false,
    ]);
}

it('creates a draft invoice from an accepted quote', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $quote = createAcceptedQuote($this);
    $currentPeriod = currentPeriodContainingToday($this);

    $invoice = $service->createFromQuote(
        $quote,
        $this->company->id,
        $this->fiscalYear->id,
        $currentPeriod->id,
        $this->journal->id,
        $this->user->id
    );

    expect($invoice->status)->toBe(InvoiceStatus::DRAFT);
    expect($invoice->quote_id)->toBe($quote->id);
    expect($invoice->customer_id)->toBe($quote->customer_id);
    expect((string) $invoice->total)->toBe('267.750');
    expect($invoice->lines()->count())->toBe(1);
});

it('keeps the source quote unchanged after conversion', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $quote = createAcceptedQuote($this);
    $currentPeriod = currentPeriodContainingToday($this);

    $service->createFromQuote(
        $quote,
        $this->company->id,
        $this->fiscalYear->id,
        $currentPeriod->id,
        $this->journal->id,
        $this->user->id
    );

    expect($quote->fresh()->status)->toBe(QuoteStatus::ACCEPTED);
});

it('rejects conversion of non accepted quotes', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $quoteService = new QuoteService;
    $data = [
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'quote_date' => '2026-01-10',
        'currency' => 'TND',
        'payment_terms_days' => 30,
        'created_by' => $this->user->id,
        'lines' => [
            [
                'product_id' => $this->product->id,
                'description' => 'x',
                'quantity' => '1.000',
                'unit' => 'unit',
                'unit_price' => '10.000',
                'discount_percent' => '0.000',
                'tax_rate_id' => $this->taxRate->id,
            ],
        ],
    ];
    $quote = $quoteService->createDraft($data);

    $service->createFromQuote(
        $quote,
        $this->company->id,
        $this->fiscalYear->id,
        $this->period->id,
        $this->journal->id,
        $this->user->id
    );
})->throws(InvalidArgumentException::class, 'devis accepté');

// ---------- Livewire pages ----------

it('renders the invoices index page', function () {
    $this->actingAs($this->user);

    Livewire::withQueryParams([])
        ->test(Index::class)
        ->assertOk();
});

it('renders the invoice creation page', function () {
    $this->actingAs($this->user);
    Livewire::test(Create::class)
        ->assertOk();
});

it('renders the invoice detail page', function () {
    $invoice = createDraftForTest($this);

    $this->actingAs($this->user);
    Livewire::test(Show::class, ['id' => $invoice->id])
        ->assertOk();
});

it('renders the invoice edit page for drafts', function () {
    $invoice = createDraftForTest($this);

    $this->actingAs($this->user);
    Livewire::test(Edit::class, ['invoiceId' => $invoice->id])
        ->assertOk();
});

it('forbids editing a posted invoice via edit page', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $invoice = createDraftForTest($this);
    $service->post($invoice, $this->user->id);

    $this->actingAs($this->user);
    Livewire::test(Edit::class, ['invoiceId' => $invoice->id])
        ->assertForbidden();
});

it('posts a draft invoice from the index page', function () {
    $invoice = createDraftForTest($this);

    $this->actingAs($this->user);
    Livewire::test(Index::class)
        ->call('post', $invoice->id)
        ->assertHasNoErrors();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::POSTED);
});

it('cancels a draft invoice from the index page', function () {
    $invoice = createDraftForTest($this);

    $this->actingAs($this->user);
    Livewire::test(Index::class)
        ->call('cancel', $invoice->id)
        ->assertHasNoErrors();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::CANCELLED);
});

it('deletes a draft invoice from the index page', function () {
    $invoice = createDraftForTest($this);

    $this->actingAs($this->user);
    Livewire::test(Index::class)
        ->call('delete', $invoice->id)
        ->assertHasNoErrors();

    expect(Invoice::find($invoice->id))->toBeNull();
});

it('filters invoices by status on the index page', function () {
    $draft = createDraftForTest($this);

    $this->actingAs($this->user);
    Livewire::test(Index::class)
        ->set('filterStatus', 'draft')
        ->assertSee($draft->invoice_number);
});

it('searches invoices by number on the index page', function () {
    $draft = createDraftForTest($this);

    $this->actingAs($this->user);
    Livewire::test(Index::class)
        ->set('search', substr((string) $draft->invoice_number, -3))
        ->assertSee($draft->invoice_number);
});
