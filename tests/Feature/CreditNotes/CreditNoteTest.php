<?php

use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Enums\JournalEntryStatus;
use App\Livewire\CreditNotes\Create;
use App\Livewire\CreditNotes\Edit;
use App\Livewire\CreditNotes\Index;
use App\Livewire\CreditNotes\Show;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\Accounting\GeneralLedgerService;
use App\Services\Accounting\JournalEntryService;
use App\Services\Accounting\TrialBalanceService;
use App\Services\CreditNoteService;
use App\Services\InvoiceService;
use App\Services\SalesCreditNotePostingService;
use App\Services\SalesInvoicePostingService;
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

function makeSourceInvoiceData(Company $company, Customer $customer, Product $product, ?TaxRate $taxRate, Journal $journal): array
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
        'created_by' => auth()->id() ?? User::factory()->create()->id,
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

function createDraftInvoiceForTest($test): Invoice
{
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $data = makeSourceInvoiceData($test->company, $test->customer, $test->product, $test->taxRate, $test->journal);
    $data['created_by'] = $test->user->id;

    $invoice = $service->createDraft($data);

    // Set accounting context required for posting (normally resolved from session).
    $invoice->update([
        'fiscal_year_id' => $test->fiscalYear->id,
        'accounting_period_id' => $test->period->id,
    ]);

    return $invoice->fresh();
}

function createPostedInvoiceForTest($test): Invoice
{
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));
    $data = makeSourceInvoiceData($test->company, $test->customer, $test->product, $test->taxRate, $test->journal);
    $data['created_by'] = $test->user->id;

    $invoice = $service->createDraft($data);
    $invoice->update([
        'fiscal_year_id' => $test->fiscalYear->id,
        'accounting_period_id' => $test->period->id,
    ]);
    $service->post($invoice->fresh(), $test->user->id);

    return $invoice->fresh();
}

function cnService(): CreditNoteService
{
    return new CreditNoteService(new SalesCreditNotePostingService(
        new JournalEntryService
    ));
}

/**
 * Build credit note lines mirroring the invoice's historical values.
 *
 * @param  array<int, string>  $qtyOverrides  invoice_line_id => quantity
 * @return array<int, array{invoice_line_id: int, product_id: int|null, description: string, quantity: string, unit: string|null, unit_price: string, discount_percent: string, tax_rate_id: int|null, tax_code: string|null, tax_rate_value: string, sales_account_id: int|null}>
 */
function makeCnLinesData(Invoice $invoice, array $qtyOverrides = []): array
{
    $lines = [];
    foreach ($invoice->lines()->orderBy('sort_order')->get() as $line) {
        $lines[] = [
            'invoice_line_id' => $line->id,
            'product_id' => $line->product_id,
            'description' => (string) $line->description,
            'quantity' => $qtyOverrides[$line->id] ?? (string) $line->quantity,
            'unit' => $line->unit,
            'unit_price' => (string) $line->unit_price,
            'discount_percent' => (string) $line->discount_percent,
            'tax_rate_id' => $line->tax_rate_id,
            'tax_code' => $line->tax_code,
            'tax_rate_value' => (string) ($line->tax_rate ?? '0'),
            'sales_account_id' => $line->sales_account_id,
        ];
    }

    return $lines;
}

/**
 * @param  array<string, mixed>  $overrides
 * @param  array<int, string>  $qtyOverrides
 */
function createDraftCreditNoteForTest($test, Invoice $invoice, array $overrides = [], array $qtyOverrides = []): CreditNote
{
    $data = array_merge([
        'company_id' => $test->company->id,
        'customer_id' => $invoice->customer_id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'accounting_period_id' => $test->period->id,
        'journal_id' => $test->journal->id,
        'invoice_id' => $invoice->id,
        'credit_note_date' => '2026-01-20',
        'reason' => 'Retour marchandise',
        'currency' => 'TND',
        'notes' => null,
        'created_by' => $test->user->id,
        'lines' => makeCnLinesData($invoice, $qtyOverrides),
    ], $overrides);

    return cnService()->createDraft($data);
}

// ---------- Draft creation ----------

it('creates a draft credit note from a posted invoice', function () {
    $invoice = createPostedInvoiceForTest($this);

    $creditNote = createDraftCreditNoteForTest($this, $invoice);

    expect($creditNote->status)->toBe(CreditNoteStatus::DRAFT)
        ->and($creditNote->company_id)->toBe($this->company->id)
        ->and($creditNote->customer_id)->toBe($invoice->customer_id)
        ->and($creditNote->invoice_id)->toBe($invoice->id)
        ->and($creditNote->currency)->toBe('TND')
        ->and($creditNote->posted_at)->toBeNull()
        ->and($creditNote->journal_entry_id)->toBeNull();
});

it('generates AV credit note numbers with the current year', function () {
    $invoice = createPostedInvoiceForTest($this);

    $first = createDraftCreditNoteForTest($this, $invoice, [], [$invoice->lines->first()->id => '1']);
    $second = createDraftCreditNoteForTest($this, $invoice, [], [$invoice->lines->first()->id => '1']);

    expect($first->credit_note_number)->toMatch('/^AV-\d{4}-\d{6}$/');
    expect($second->credit_note_number)->not->toBe($first->credit_note_number);
});

// ---------- Source invoice validation ----------

it('rejects a source invoice from another company', function () {
    $invoice = createPostedInvoiceForTest($this);

    createDraftCreditNoteForTest($this, $invoice, ['company_id' => 999999]);
})->throws(InvalidArgumentException::class, 'n\'appartient pas à cette société');

it('rejects a draft invoice as source', function () {
    $invoice = createDraftInvoiceForTest($this);

    createDraftCreditNoteForTest($this, $invoice);
})->throws(InvalidArgumentException::class, 'Seule une facture comptabilisée');

it('rejects a cancelled invoice as source', function () {
    $invoice = createPostedInvoiceForTest($this);
    DB::table('invoices')->where('id', $invoice->id)->update(['status' => 'cancelled']);

    createDraftCreditNoteForTest($this, $invoice->fresh());
})->throws(InvalidArgumentException::class, 'Seule une facture comptabilisée');

it('requires the credit note customer to match the invoice customer', function () {
    $invoice = createPostedInvoiceForTest($this);
    $otherCustomer = Customer::create([
        'company_id' => $this->company->id,
        'code' => 'CL002',
        'name' => 'Autre Client',
        'customer_type' => 'individual',
        'country' => 'TN',
        'is_active' => true,
    ]);

    createDraftCreditNoteForTest($this, $invoice, ['customer_id' => $otherCustomer->id]);
})->throws(InvalidArgumentException::class, 'doit correspondre au client de la facture');

it('requires a valid journal entry on the source invoice', function () {
    $invoice = createPostedInvoiceForTest($this);
    DB::table('invoices')->where('id', $invoice->id)->update(['journal_entry_id' => null]);

    createDraftCreditNoteForTest($this, $invoice->fresh());
})->throws(InvalidArgumentException::class, 'écriture comptable valide');

// ---------- Partial / full credit & availability ----------

it('creates a partial credit note with proportional totals', function () {
    $invoice = createPostedInvoiceForTest($this);
    $invoiceLineId = $invoice->lines->first()->id;

    $creditNote = createDraftCreditNoteForTest($this, $invoice, [], [$invoiceLineId => '4']);

    expect($creditNote->subtotal)->toBe('90.000')
        ->and($creditNote->discount_total)->toBe('10.000')
        ->and($creditNote->tax_total)->toBe('17.100')
        ->and($creditNote->total)->toBe('107.100');
});

it('creates a full credit note equal to the invoice totals', function () {
    $invoice = createPostedInvoiceForTest($this);

    $creditNote = createDraftCreditNoteForTest($this, $invoice);

    expect($creditNote->subtotal)->toBe('225.000')
        ->and($creditNote->discount_total)->toBe('25.000')
        ->and($creditNote->tax_total)->toBe('42.750')
        ->and($creditNote->total)->toBe('267.750');
});

it('calculates already credited and remaining quantities after a partial posting', function () {
    $service = cnService();
    $invoice = createPostedInvoiceForTest($this);
    $invoiceLineId = $invoice->lines->first()->id;

    $creditNote = createDraftCreditNoteForTest($this, $invoice, [], [$invoiceLineId => '4']);
    $service->post($creditNote, $this->user->id);

    $remaining = $service->determineRemainingAmounts($invoice->fresh());

    expect($remaining[$invoiceLineId]['original_quantity'])->toBe('10.000')
        ->and($remaining[$invoiceLineId]['credited_quantity'])->toBe('4.000')
        ->and($remaining[$invoiceLineId]['remaining_quantity'])->toBe('6.000');
});

it('rejects crediting more than the available quantity', function () {
    $service = cnService();
    $invoice = createPostedInvoiceForTest($this);
    $invoiceLineId = $invoice->lines->first()->id;

    $creditNote = createDraftCreditNoteForTest($this, $invoice, [], [$invoiceLineId => '4']);
    $service->post($creditNote, $this->user->id);

    createDraftCreditNoteForTest($this, $invoice, [], [$invoiceLineId => '7']);
})->throws(InvalidArgumentException::class, 'supérieure au disponible');

it('rejects zero quantity lines', function () {
    $invoice = createPostedInvoiceForTest($this);

    createDraftCreditNoteForTest($this, $invoice, [], [$invoice->lines->first()->id => '0']);
})->throws(InvalidArgumentException::class, 'strictement positive');

it('rejects a fully credited invoice for another credit note', function () {
    $service = cnService();
    $invoice = createPostedInvoiceForTest($this);

    $full = createDraftCreditNoteForTest($this, $invoice);
    $service->post($full, $this->user->id);

    cnService()->createFromInvoice(
        $invoice->fresh(),
        $this->company->id,
        $this->fiscalYear->id,
        $this->period->id,
        $this->journal->id,
        $this->user->id,
        '2026-01-21'
    );
})->throws(InvalidArgumentException::class, 'totalement créditée');

it('protects against concurrent over-crediting at posting time', function () {
    $service = cnService();
    $invoice = createPostedInvoiceForTest($this);
    $invoiceLineId = $invoice->lines->first()->id;

    // Both drafts are created before either is posted; each credits the full quantity.
    $first = createDraftCreditNoteForTest($this, $invoice, ['credit_note_date' => '2026-01-20'], [$invoiceLineId => '10']);
    $second = createDraftCreditNoteForTest($this, $invoice, ['credit_note_date' => '2026-01-21'], [$invoiceLineId => '10']);

    $service->post($first, $this->user->id);
    $service->post($second->fresh(), $this->user->id);
})->throws(InvalidArgumentException::class, 'entre-temps');

it('allows only the credited quantities that remain after a competing posting', function () {
    $service = cnService();
    $invoice = createPostedInvoiceForTest($this);
    $invoiceLineId = $invoice->lines->first()->id;

    $first = createDraftCreditNoteForTest($this, $invoice, ['credit_note_date' => '2026-01-20'], [$invoiceLineId => '8']);
    $second = createDraftCreditNoteForTest($this, $invoice, ['credit_note_date' => '2026-01-21'], [$invoiceLineId => '2']);

    $service->post($first, $this->user->id);
    $service->post($second->fresh(), $this->user->id);

    $totalCredited = DB::table('credit_note_lines')
        ->join('credit_notes', 'credit_notes.id', '=', 'credit_note_lines.credit_note_id')
        ->where('credit_notes.invoice_id', $invoice->id)
        ->where('credit_notes.status', CreditNoteStatus::POSTED->value)
        ->sum('credit_note_lines.quantity');

    expect((string) $totalCredited)->toBe('10');
});

// ---------- Calculations ----------

it('calculates line totals with BCMath precision', function () {
    $service = cnService();

    $calculated = $service->calculateLine('3.000', '25.000', '10.000', '19.0');

    expect($calculated['gross'] ?? null)->toBeNull()
        ->and($calculated['discount_amount'])->toBe('7.500')
        ->and($calculated['line_subtotal'])->toBe('67.500')
        ->and($calculated['tax_amount'])->toBe('12.825')
        ->and($calculated['line_total'])->toBe('80.325');
});

// ---------- Lifecycle ----------

it('updates a draft credit note and recalculates totals', function () {
    $invoice = createPostedInvoiceForTest($this);
    $invoiceLineId = $invoice->lines->first()->id;

    $creditNote = createDraftCreditNoteForTest($this, $invoice);

    $updated = cnService()->updateDraft($creditNote, [
        'reason' => 'Erreur de facturation',
        'notes' => 'Correction',
        'lines' => [
            ['invoice_line_id' => $invoiceLineId, 'quantity' => '5'],
        ],
    ]);

    expect($updated->reason)->toBe('Erreur de facturation')
        ->and($updated->subtotal)->toBe('112.500')
        ->and($updated->tax_total)->toBe('21.375')
        ->and($updated->total)->toBe('133.875');
});

it('refuses to update a non-draft credit note', function () {
    $service = cnService();
    $invoice = createPostedInvoiceForTest($this);

    $creditNote = createDraftCreditNoteForTest($this, $invoice);
    $service->post($creditNote, $this->user->id);

    $service->updateDraft($creditNote->fresh(), [
        'lines' => [['invoice_line_id' => $invoice->lines->first()->id, 'quantity' => '1']],
    ]);
})->throws(InvalidArgumentException::class, 'brouillon peut être modifié');

it('deletes a draft credit note with its lines', function () {
    $invoice = createPostedInvoiceForTest($this);

    $creditNote = createDraftCreditNoteForTest($this, $invoice);

    cnService()->deleteDraft($creditNote);

    expect(CreditNote::find($creditNote->id))->toBeNull()
        ->and(DB::table('credit_note_lines')->where('credit_note_id', $creditNote->id)->count())->toBe(0);
});

it('cancels a draft credit note', function () {
    $invoice = createPostedInvoiceForTest($this);

    $creditNote = createDraftCreditNoteForTest($this, $invoice);

    $cancelled = cnService()->cancel($creditNote);

    expect($cancelled->status)->toBe(CreditNoteStatus::CANCELLED);
});

it('refuses to cancel a posted credit note', function () {
    $service = cnService();
    $invoice = createPostedInvoiceForTest($this);

    $creditNote = createDraftCreditNoteForTest($this, $invoice);
    $service->post($creditNote, $this->user->id);

    $service->cancel($creditNote->fresh());
})->throws(InvalidArgumentException::class, 'brouillon peut être annulé');

it('refuses to delete a posted credit note', function () {
    $service = cnService();
    $invoice = createPostedInvoiceForTest($this);

    $creditNote = createDraftCreditNoteForTest($this, $invoice);
    $service->post($creditNote, $this->user->id);

    $service->deleteDraft($creditNote->fresh());
})->throws(InvalidArgumentException::class, 'brouillon peut être supprimé');

// ---------- Posting & accounting ----------

it('posts a credit note creating a balanced journal entry', function () {
    $service = cnService();
    $invoice = createPostedInvoiceForTest($this);
    $invoiceLineId = $invoice->lines->first()->id;

    $creditNote = createDraftCreditNoteForTest($this, $invoice, [], [$invoiceLineId => '4']);
    $posted = $service->post($creditNote, $this->user->id);

    $entry = $posted->journalEntry;

    expect($entry)->not->toBeNull()
        ->and($entry->status)->toBe(JournalEntryStatus::POSTED)
        ->and($entry->reference)->toBe($posted->credit_note_number)
        ->and($entry->entry_date->toDateString())->toBe('2026-01-20')
        ->and($entry->totalDebit())->toBe($entry->totalCredit())
        ->and($entry->totalDebit())->toBe('107.100');
});

it('debits the sales account in the corrective entry', function () {
    $service = cnService();
    $invoice = createPostedInvoiceForTest($this);
    $invoiceLineId = $invoice->lines->first()->id;

    $creditNote = createDraftCreditNoteForTest($this, $invoice, [], [$invoiceLineId => '4']);
    $service->post($creditNote, $this->user->id);

    $debit707 = $creditNote->fresh()->journalEntry->lines()
        ->where('account_id', $this->account707->id)
        ->first();

    expect($debit707)->not->toBeNull()
        ->and((string) $debit707->debit)->toBe('90.000')
        ->and((string) $debit707->credit)->toBe('0.000');
});

it('debits the VAT account in the corrective entry', function () {
    $service = cnService();
    $invoice = createPostedInvoiceForTest($this);
    $invoiceLineId = $invoice->lines->first()->id;

    $creditNote = createDraftCreditNoteForTest($this, $invoice, [], [$invoiceLineId => '4']);
    $service->post($creditNote, $this->user->id);

    $debit4457 = $creditNote->fresh()->journalEntry->lines()
        ->where('account_id', $this->account4457->id)
        ->first();

    expect($debit4457)->not->toBeNull()
        ->and((string) $debit4457->debit)->toBe('17.100');
});

it('credits the customer receivable account in the corrective entry', function () {
    $service = cnService();
    $invoice = createPostedInvoiceForTest($this);
    $invoiceLineId = $invoice->lines->first()->id;

    $creditNote = createDraftCreditNoteForTest($this, $invoice, [], [$invoiceLineId => '4']);
    $service->post($creditNote, $this->user->id);

    $credit411 = $creditNote->fresh()->journalEntry->lines()
        ->where('account_id', $this->account411->id)
        ->where('credit', '>', 0)
        ->first();

    expect($credit411)->not->toBeNull()
        ->and((string) $credit411->credit)->toBe('107.100');
});

it('marks the credit note as posted with its entry link', function () {
    $service = cnService();
    $invoice = createPostedInvoiceForTest($this);

    $creditNote = createDraftCreditNoteForTest($this, $invoice);
    $posted = $service->post($creditNote, $this->user->id);

    expect($posted->status)->toBe(CreditNoteStatus::POSTED)
        ->and($posted->posted_at)->not->toBeNull()
        ->and($posted->journal_entry_id)->not->toBeNull();
});

it('keeps the original invoice unchanged after posting a credit note', function () {
    $service = cnService();
    $invoice = createPostedInvoiceForTest($this);

    $before = [
        'status' => $invoice->status,
        'total' => (string) $invoice->total,
        'entry_id' => $invoice->journal_entry_id,
        'lines_count' => $invoice->lines()->count(),
        'entry_lines' => $invoice->journalEntry->lines()->orderBy('id')->get(['account_id', 'debit', 'credit'])->toJson(),
    ];

    $creditNote = createDraftCreditNoteForTest($this, $invoice, [], [$invoice->lines->first()->id => '4']);
    $service->post($creditNote, $this->user->id);

    $after = $invoice->fresh();

    expect($after->status)->toBe($before['status'])
        ->and($after->status)->toBe(InvoiceStatus::POSTED)
        ->and((string) $after->total)->toBe($before['total'])
        ->and($after->journal_entry_id)->toBe($before['entry_id'])
        ->and($after->lines()->count())->toBe($before['lines_count'])
        ->and($after->journalEntry->lines()->orderBy('id')->get(['account_id', 'debit', 'credit'])->toJson())
        ->toBe($before['entry_lines']);
});

it('blocks posting when the fiscal year is closed', function () {
    $service = cnService();
    $invoice = createPostedInvoiceForTest($this);

    $creditNote = createDraftCreditNoteForTest($this, $invoice);
    $this->fiscalYear->update(['is_closed' => true]);

    $service->post($creditNote, $this->user->id);
})->throws(InvalidArgumentException::class, 'exercice comptable est clôturé');

it('blocks posting when the accounting period is closed', function () {
    $service = cnService();
    $invoice = createPostedInvoiceForTest($this);

    $creditNote = createDraftCreditNoteForTest($this, $invoice);
    $this->period->update(['is_open' => false]);

    $service->post($creditNote, $this->user->id);
})->throws(InvalidArgumentException::class, 'période comptable est clôturée');

it('rejects a credit note date outside the current period', function () {
    $invoice = createPostedInvoiceForTest($this);

    createDraftCreditNoteForTest($this, $invoice, ['credit_note_date' => '2026-02-05']);
})->throws(InvalidArgumentException::class, 'comprise dans la période comptable');

it('allows the corrective entry in a different open period than the source invoice', function () {
    $service = cnService();
    $invoice = createPostedInvoiceForTest($this);

    $februaryPeriod = AccountingPeriod::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'name' => 'Février 2026',
        'code' => '2026-02',
        'start_date' => '2026-02-01',
        'end_date' => '2026-02-28',
        'is_open' => true,
        'is_closed' => false,
    ]);

    $creditNote = createDraftCreditNoteForTest($this, $invoice, [
        'accounting_period_id' => $februaryPeriod->id,
        'credit_note_date' => '2026-02-05',
    ]);
    $posted = $service->post($creditNote, $this->user->id);

    expect($posted->journalEntry->accounting_period_id)->toBe($februaryPeriod->id)
        ->and($invoice->fresh()->journalEntry->accounting_period_id)->toBe($this->period->id);
});

// ---------- Master data isolation ----------

it('rejects a product from another company in credit lines', function () {
    $invoice = createPostedInvoiceForTest($this);
    $lines = makeCnLinesData($invoice);
    $lines[0]['product_id'] = 999999;

    createDraftCreditNoteForTest($this, $invoice, ['lines' => $lines]);
})->throws(InvalidArgumentException::class, 'n\'appartient pas à cette société');

// ---------- Authorization ----------

it('restricts mutations to company admins', function () {
    $service = cnService();
    $invoice = createPostedInvoiceForTest($this);

    $accountant = User::factory()->create();
    $this->company->users()->attach($accountant, ['role' => 'accountant', 'is_active' => true]);

    $creditNote = createDraftCreditNoteForTest($this, $invoice);

    expect($accountant->can('viewAny', CreditNote::class))->toBeTrue()
        ->and($accountant->can('view', $creditNote))->toBeTrue()
        ->and($accountant->can('create', [CreditNote::class, $this->company]))->toBeFalse()
        ->and($accountant->can('update', $creditNote))->toBeFalse()
        ->and($accountant->can('delete', $creditNote))->toBeFalse()
        ->and($accountant->can('post', $creditNote))->toBeFalse()
        ->and($accountant->can('cancel', $creditNote))->toBeFalse()
        ->and($this->user->can('create', [CreditNote::class, $this->company]))->toBeTrue()
        ->and($this->user->can('update', $creditNote))->toBeTrue()
        ->and($this->user->can('post', $creditNote))->toBeTrue();
});

it('blocks cross-company access to credit notes', function () {
    $invoice = createPostedInvoiceForTest($this);
    $creditNote = createDraftCreditNoteForTest($this, $invoice);

    $outsider = User::factory()->create();

    expect($outsider->can('view', $creditNote))->toBeFalse();
});

// ---------- General Ledger / Trial Balance integration ----------

it('shows the corrective movement in the general ledger', function () {
    $service = cnService();
    $invoice = createPostedInvoiceForTest($this);
    $invoiceLineId = $invoice->lines->first()->id;

    $creditNote = createDraftCreditNoteForTest($this, $invoice, [], [$invoiceLineId => '4']);
    $posted = $service->post($creditNote, $this->user->id);

    $glService = new GeneralLedgerService;

    $ledger707 = $glService->getAccountLedger($this->account707, $this->company, $this->fiscalYear);
    expect($ledger707->contains(fn ($movement) => bccomp((string) $movement->debit, '90.000', 3) === 0 && $movement->reference === $posted->credit_note_number))->toBeTrue();

    $ledger411 = $glService->getAccountLedger($this->account411, $this->company, $this->fiscalYear);
    expect($ledger411->contains(fn ($movement) => bccomp((string) $movement->credit, '107.100', 3) === 0 && $movement->reference === $posted->credit_note_number))->toBeTrue();
});

it('keeps the trial balance balanced including the credit note', function () {
    $service = cnService();
    $invoice = createPostedInvoiceForTest($this);
    $invoiceLineId = $invoice->lines->first()->id;

    $creditNote = createDraftCreditNoteForTest($this, $invoice, [], [$invoiceLineId => '4']);
    $service->post($creditNote, $this->user->id);

    $trialBalance = (new TrialBalanceService)->getTrialBalance($this->company, $this->fiscalYear);

    // Invoice entry (267.750) + corrective entry (107.100).
    expect($trialBalance['total_debit'])->toBe('374.850')
        ->and($trialBalance['total_credit'])->toBe('374.850')
        ->and($trialBalance['is_balanced'])->toBeTrue();

    $totalsByCode = $trialBalance['accounts']->mapWithKeys(fn ($row) => [$row->code => $row]);
    $format = fn ($value): string => number_format((float) $value, 3, '.', '');
    expect($format($totalsByCode['707000']->total_debit))->toBe('90.000')
        ->and($format($totalsByCode['707000']->total_credit))->toBe('225.000')
        ->and($format($totalsByCode['411000']->total_debit))->toBe('267.750')
        ->and($format($totalsByCode['411000']->total_credit))->toBe('107.100');
});

// ---------- Livewire pages ----------

it('renders the credit notes index page', function () {
    $this->actingAs($this->user);

    Livewire::withQueryParams([])
        ->test(Index::class)
        ->assertOk();
});

it('renders the credit note creation page without an invoice', function () {
    $this->actingAs($this->user);

    Livewire::test(Create::class)
        ->assertOk();
});

it('loads available lines when creating from an invoice', function () {
    $invoice = createPostedInvoiceForTest($this);

    $this->actingAs($this->user);
    Livewire::test(Create::class, ['invoice' => $invoice->id])
        ->assertOk()
        ->assertSee($invoice->invoice_number)
        ->assertSee('Produit Test');
});

it('renders the credit note detail page', function () {
    $invoice = createPostedInvoiceForTest($this);
    $creditNote = createDraftCreditNoteForTest($this, $invoice);

    $this->actingAs($this->user);
    Livewire::test(Show::class, ['creditNoteId' => $creditNote->id])
        ->assertOk()
        ->assertSee($creditNote->credit_note_number);
});

it('renders the credit note edit page for drafts', function () {
    $invoice = createPostedInvoiceForTest($this);
    $creditNote = createDraftCreditNoteForTest($this, $invoice);

    $this->actingAs($this->user);
    Livewire::test(Edit::class, ['creditNoteId' => $creditNote->id])
        ->assertOk();
});

it('forbids editing a posted credit note via edit page', function () {
    $service = cnService();
    $invoice = createPostedInvoiceForTest($this);
    $creditNote = createDraftCreditNoteForTest($this, $invoice);
    $service->post($creditNote, $this->user->id);

    $this->actingAs($this->user);
    Livewire::test(Edit::class, ['creditNoteId' => $creditNote->id])
        ->assertRedirect(route('credit-notes.show', $creditNote->id));
});

it('posts a draft credit note from the index page', function () {
    $invoice = createPostedInvoiceForTest($this);
    $creditNote = createDraftCreditNoteForTest($this, $invoice);

    $this->actingAs($this->user);
    Livewire::test(Index::class)
        ->call('post', $creditNote->id)
        ->assertHasNoErrors();

    expect($creditNote->fresh()->status)->toBe(CreditNoteStatus::POSTED);
});

it('cancels a draft credit note from the index page', function () {
    $invoice = createPostedInvoiceForTest($this);
    $creditNote = createDraftCreditNoteForTest($this, $invoice);

    $this->actingAs($this->user);
    Livewire::test(Index::class)
        ->call('cancel', $creditNote->id)
        ->assertHasNoErrors();

    expect($creditNote->fresh()->status)->toBe(CreditNoteStatus::CANCELLED);
});

it('deletes a draft credit note from the index page', function () {
    $invoice = createPostedInvoiceForTest($this);
    $creditNote = createDraftCreditNoteForTest($this, $invoice);

    $this->actingAs($this->user);
    Livewire::test(Index::class)
        ->call('delete', $creditNote->id)
        ->assertHasNoErrors();

    expect(CreditNote::find($creditNote->id))->toBeNull();
});

it('filters credit notes by status on the index page', function () {
    $invoice = createPostedInvoiceForTest($this);
    $draft = createDraftCreditNoteForTest($this, $invoice, [], [$invoice->lines->first()->id => '1']);

    $this->actingAs($this->user);
    Livewire::test(Index::class)
        ->set('filterStatus', 'draft')
        ->assertSee($draft->credit_note_number);
});

it('searches credit notes by number on the index page', function () {
    $invoice = createPostedInvoiceForTest($this);
    $creditNote = createDraftCreditNoteForTest($this, $invoice);

    $this->actingAs($this->user);
    Livewire::test(Index::class)
        ->set('search', substr((string) $creditNote->credit_note_number, -3))
        ->assertSee($creditNote->credit_note_number);
});

it('paginates credit notes on the index page', function () {
    $invoice = createPostedInvoiceForTest($this);
    $oldest = createDraftCreditNoteForTest($this, $invoice, [], [$invoice->lines->first()->id => '1']);
    for ($i = 0; $i < 15; $i++) {
        createDraftCreditNoteForTest($this, $invoice, [], [$invoice->lines->first()->id => '1']);
    }

    expect(CreditNote::count())->toBe(16);

    $this->actingAs($this->user);
    Livewire::test(Index::class)
        ->assertOk()
        ->assertDontSee($oldest->credit_note_number, false)
        ->set('perPage', 50)
        ->assertSee($oldest->credit_note_number, false);
});

it('scopes credit notes listing to the current company', function () {
    $otherUser = User::factory()->create();
    $otherCompany = Company::create(['name' => 'Other Co', 'currency' => 'TND', 'is_active' => true]);
    $otherCompany->users()->attach($otherUser, ['role' => 'admin', 'is_active' => true]);

    $invoice = createPostedInvoiceForTest($this);
    $creditNote = createDraftCreditNoteForTest($this, $invoice);

    $this->actingAs($otherUser);
    Livewire::test(Index::class)
        ->assertDontSee($creditNote->credit_note_number);
});
