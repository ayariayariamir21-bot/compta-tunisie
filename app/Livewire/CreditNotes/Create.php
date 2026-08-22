<?php

namespace App\Livewire\CreditNotes;

use App\Enums\InvoiceStatus;
use App\Models\CompanyAccountingSetting;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Journal;
use App\Services\CreditNoteService;
use App\Services\CurrentAccountingPeriod;
use App\Services\CurrentCompany;
use App\Services\CurrentFiscalYear;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Nouvel avoir')]
class Create extends Component
{
    public ?int $invoice = null;

    public string $credit_note_date = '';

    public ?string $reason = null;

    public ?string $notes = null;

    public bool $saveAndPost = false;

    /**
     * Editable credit quantities per source invoice line, with historical
     * values copied from the invoice line.
     *
     * @var array<int, array{invoice_line_id: int, product_id: int, description: string, original_quantity: numeric-string, credited_quantity: numeric-string, available_quantity: numeric-string, quantity: string, unit: string, unit_price: string, discount_percent: string, tax_label: string, tax_rate_value: numeric-string}>
     */
    public array $lines = [];

    public function mount(?int $invoice = null): void
    {
        $this->credit_note_date = now()->toDateString();

        if ($invoice !== null) {
            $this->loadInvoice($invoice);
        }
    }

    public function loadInvoice(int $invoiceId): void
    {
        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        if (! $currentCompany) {
            return;
        }

        $invoice = Invoice::with('lines')
            ->where('id', $invoiceId)
            ->where('company_id', $currentCompany->id)
            ->first();

        if (! $invoice || $invoice->status !== InvoiceStatus::POSTED) {
            session()->flash('error', 'Seule une facture comptabilisée peut faire l\'objet d\'un avoir.');

            return;
        }

        $remaining = app(CreditNoteService::class)->determineRemainingAmounts($invoice);

        $lines = [];
        foreach ($remaining as $entry) {
            if (bccomp($entry['remaining_quantity'], '0', 3) <= 0) {
                continue;
            }

            $line = $entry['line'];
            $taxLabel = $line->tax_code !== null
                ? $line->tax_code.' ('.rtrim(rtrim((string) $line->tax_rate, '0'), '.').'%)'
                : 'Sans TVA';

            $lines[] = [
                'invoice_line_id' => (int) $line->id,
                'product_id' => (int) $line->product_id,
                'description' => $line->description,
                'original_quantity' => $entry['original_quantity'],
                'credited_quantity' => $entry['credited_quantity'],
                'available_quantity' => $entry['remaining_quantity'],
                'quantity' => $entry['remaining_quantity'],
                'unit' => $line->unit,
                'unit_price' => (string) $line->unit_price,
                'discount_percent' => (string) $line->discount_percent,
                'tax_label' => $taxLabel,
                'tax_rate_value' => (string) $line->tax_rate,
            ];
        }

        if ($lines === []) {
            session()->flash('error', 'Cette facture est déjà totalement créditée.');
            $this->invoice = null;
            $this->lines = [];

            return;
        }

        $this->invoice = $invoiceId;
        $this->lines = $lines;
    }

    /** @return array{subtotal: numeric-string, discount_total: numeric-string, tax_total: numeric-string, total: numeric-string} */
    public function getTotalsProperty(): array
    {
        bcscale(3);

        $subtotal = '0';
        $discountTotal = '0';
        $taxTotal = '0';
        $total = '0';

        foreach ($this->lines as $line) {
            if (bccomp($this->normalize($line['quantity']), '0', 3) <= 0) {
                continue;
            }

            $calculated = app(CreditNoteService::class)->calculateLine(
                $line['quantity'],
                $line['unit_price'],
                $line['discount_percent'],
                $line['tax_rate_value']
            );

            $subtotal = bcadd($subtotal, $calculated['line_subtotal'], 3);
            $discountTotal = bcadd($discountTotal, $calculated['discount_amount'], 3);
            $taxTotal = bcadd($taxTotal, $calculated['tax_amount'], 3);
            $total = bcadd($total, $calculated['line_total'], 3);
        }

        return [
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'total' => $total,
        ];
    }

    public function save(bool $withPost, CreditNoteService $creditNoteService, CurrentCompany $currentCompany, CurrentFiscalYear $currentFiscalYear, CurrentAccountingPeriod $currentAccountingPeriod): void
    {
        $this->saveAndPost = $withPost;

        $company = $currentCompany->get(Auth::user());
        $fiscalYear = $currentFiscalYear->get(Auth::user());
        $accountingPeriod = $currentAccountingPeriod->get(Auth::user());

        if (! $company || ! $fiscalYear || ! $accountingPeriod) {
            session()->flash('error', 'Contexte comptable incomplet. Veuillez sélectionner une société, un exercice et une période.');

            return;
        }

        if ($this->invoice === null) {
            $this->addError('invoice', 'Veuillez sélectionner une facture source.');

            return;
        }

        if (Auth::user()->cannot('create', [CreditNote::class, $company])) {
            abort(403);
        }

        $this->validate([
            'credit_note_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $invoice = Invoice::with('lines')
            ->where('id', $this->invoice)
            ->where('company_id', $company->id)
            ->first();

        if (! $invoice || $invoice->status !== InvoiceStatus::POSTED) {
            $this->addError('invoice', 'Seule une facture comptabilisée peut faire l\'objet d\'un avoir.');

            return;
        }

        $historical = [];
        foreach ($creditNoteService->determineRemainingAmounts($invoice) as $entry) {
            $line = $entry['line'];
            $historical[(int) $line->id] = [
                'product_id' => (int) $line->product_id,
                'description' => $line->description,
                'unit' => $line->unit,
                'unit_price' => (string) $line->unit_price,
                'discount_percent' => (string) $line->discount_percent,
                'tax_rate_id' => $line->tax_rate_id,
                'tax_code' => $line->tax_code,
                'tax_rate_value' => (string) $line->tax_rate,
                'sales_account_id' => $line->sales_account_id,
            ];
        }

        $linesData = [];
        foreach ($this->lines as $inputLine) {
            $quantity = $creditNoteService->toDecimal($inputLine['quantity']);
            if (bccomp($quantity, '0', 3) <= 0) {
                continue;
            }

            $source = $historical[(int) $inputLine['invoice_line_id']] ?? null;
            if ($source === null) {
                $this->addError('invoice', 'La ligne créditée ne fait pas partie de la facture source.');

                return;
            }

            $linesData[] = array_merge(
                ['invoice_line_id' => (int) $inputLine['invoice_line_id'], 'quantity' => $quantity],
                $source
            );
        }

        if ($linesData === []) {
            $this->addError('lines', 'Un avoir doit contenir au moins une ligne avec une quantité positive.');

            return;
        }

        try {
            $creditNote = $creditNoteService->createDraft([
                'company_id' => $company->id,
                'customer_id' => $invoice->customer_id,
                'fiscal_year_id' => $fiscalYear->id,
                'accounting_period_id' => $accountingPeriod->id,
                'journal_id' => $this->resolveJournalId($company->id),
                'invoice_id' => $invoice->id,
                'credit_note_date' => $this->credit_note_date,
                'reason' => $this->reason,
                'currency' => $invoice->currency,
                'notes' => $this->notes,
                'created_by' => (int) Auth::id(),
                'lines' => $linesData,
            ]);

            if ($this->saveAndPost) {
                $creditNoteService->post($creditNote, (int) Auth::id());
            }
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $label = $this->saveAndPost ? 'créé et comptabilisé' : 'créé';
        session()->flash('success', "L'avoir « {$creditNote->credit_note_number} » a été {$label}.");
        $this->redirect(route('credit-notes.show', $creditNote->id), navigate: true);
    }

    private function resolveJournalId(int $companyId): ?int
    {
        // Preferred journal: Accounting Settings → default sales journal.
        $settings = CompanyAccountingSetting::where('company_id', $companyId)->first();
        if ($settings && $settings->default_sales_journal_id) {
            return (int) $settings->default_sales_journal_id;
        }

        // Fallback: the active "ventes" journal of the current fiscal year.
        $fy = app(CurrentFiscalYear::class)->get(Auth::user());

        $journal = Journal::where('company_id', $companyId)
            ->where('type', 'ventes')
            ->where('is_active', true)
            ->when($fy, fn ($q) => $q->where('fiscal_year_id', $fy->id))
            ->orderBy('id')
            ->first();

        return $journal?->id;
    }

    /**
     * @return numeric-string
     */
    private function normalize(string $value): string
    {
        return app(CreditNoteService::class)->toDecimal($value);
    }

    public function render(CurrentCompany $currentCompany): View
    {
        $company = $currentCompany->get(Auth::user());
        $invoices = collect();

        if ($company && $this->invoice === null) {
            $invoices = Invoice::where('company_id', $company->id)
                ->where('status', InvoiceStatus::POSTED->value)
                ->with('customer')
                ->orderByDesc('invoice_date')
                ->get()
                ->filter(fn (Invoice $candidate): bool => $this->hasAvailableQuantity($candidate));
        }

        return view('livewire.credit-notes.create', [
            'currentCompany' => $company,
            'invoices' => $invoices,
        ]);
    }

    private function hasAvailableQuantity(Invoice $invoice): bool
    {
        foreach (app(CreditNoteService::class)->determineRemainingAmounts($invoice) as $entry) {
            if (bccomp($entry['remaining_quantity'], '0', 3) > 0) {
                return true;
            }
        }

        return false;
    }
}
