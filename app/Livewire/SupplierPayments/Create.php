<?php

namespace App\Livewire\SupplierPayments;

use App\Enums\PurchaseInvoiceStatus;
use App\Enums\SupplierPaymentStatus;
use App\Models\Account;
use App\Models\Journal;
use App\Models\PaymentMethod;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\CurrentAccountingPeriod;
use App\Services\CurrentCompany;
use App\Services\CurrentFiscalYear;
use App\Services\SupplierPaymentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read array{allocated: numeric-string, unallocated: numeric-string} $totals
 */
#[Layout('layouts.app')]
#[Title('Nouveau règlement fournisseur')]
class Create extends Component
{
    public ?int $supplier_id = null;

    public string $payment_date = '';

    public ?int $payment_method_id = null;

    public ?int $journal_id = null;

    public ?int $destination_account_id = null;

    public string $amount = '';

    public ?string $reference = null;

    public ?string $notes = null;

    public bool $saveAndPost = false;

    /**
     * Editable allocation rows per posted invoice of the selected supplier.
     *
     * @var array<int, array{purchase_invoice_id: int, number: string, date: string, total: string, paid: string, remaining: string, amount: string}>
     */
    public array $allocations = [];

    public function mount(?int $invoice = null): void
    {
        $this->payment_date = now()->toDateString();

        if ($invoice !== null) {
            $this->loadInvoice($invoice);
        } else {
            $this->loadDefaultMethod();
        }
    }

    /**
     * Preselect a supplier and an invoice allocation when coming from an invoice.
     */
    public function loadInvoice(int $invoiceId): void
    {
        $company = app(CurrentCompany::class)->get(Auth::user());
        if (! $company) {
            return;
        }

        /** @var PurchaseInvoice|null $invoice */
        $invoice = PurchaseInvoice::where('id', $invoiceId)
            ->where('company_id', $company->id)
            ->first();

        if (! $invoice || $invoice->status !== PurchaseInvoiceStatus::POSTED) {
            session()->flash('error', 'Seule une facture comptabilisée peut faire l\'objet d\'un règlement.');

            return;
        }

        $remaining = app(SupplierPaymentService::class)->getInvoiceRemainingAmount($invoice);

        if (bccomp($remaining, '0', 3) <= 0) {
            session()->flash('error', 'Cette facture est déjà totalement réglée.');

            return;
        }

        $this->supplier_id = $invoice->supplier_id;
        $this->amount = $remaining;

        $this->loadSupplierInvoices();

        foreach ($this->allocations as $index => $row) {
            if ($row['purchase_invoice_id'] === (int) $invoice->id) {
                $this->allocations[$index]['amount'] = $remaining;
            }
        }
    }

    private function loadDefaultMethod(): void
    {
        $company = app(CurrentCompany::class)->get(Auth::user());
        if (! $company) {
            return;
        }

        /** @var PaymentMethod|null $method */
        $method = PaymentMethod::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if ($method) {
            $this->payment_method_id = $method->id;
            $this->resolveAccountingDefaults();
        }
    }

    public function updatedSupplierId(): void
    {
        $this->loadSupplierInvoices();
    }

    public function updatedPaymentMethodId(): void
    {
        $this->resolveAccountingDefaults();
    }

    private function loadSupplierInvoices(): void
    {
        $this->allocations = [];

        if ($this->supplier_id === null) {
            return;
        }

        $company = app(CurrentCompany::class)->get(Auth::user());
        if (! $company) {
            return;
        }

        $service = app(SupplierPaymentService::class);

        $invoices = PurchaseInvoice::where('company_id', $company->id)
            ->where('supplier_id', $this->supplier_id)
            ->where('status', PurchaseInvoiceStatus::POSTED)
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->get();

        foreach ($invoices as $invoice) {
            $total = $service->toDecimal((string) $invoice->total);
            $paid = $service->getPostedAllocatedAmount($invoice);
            $remaining = bcsub($total, $paid, 3);

            if (bccomp($remaining, '0', 3) <= 0) {
                continue;
            }

            $this->allocations[] = [
                'purchase_invoice_id' => (int) $invoice->id,
                'number' => $invoice->invoice_number,
                'date' => $invoice->invoice_date->format('d/m/Y'),
                'total' => $total,
                'paid' => $paid,
                'remaining' => $remaining,
                'amount' => '0.000',
            ];
        }
    }

    private function resolveAccountingDefaults(): void
    {
        $company = app(CurrentCompany::class)->get(Auth::user());
        $fiscalYear = app(CurrentFiscalYear::class)->get(Auth::user());

        if (! $company || ! $fiscalYear || $this->payment_method_id === null) {
            return;
        }

        /** @var PaymentMethod|null $method */
        $method = PaymentMethod::where('id', $this->payment_method_id)
            ->where('company_id', $company->id)
            ->first();

        if (! $method) {
            return;
        }

        $service = app(SupplierPaymentService::class);

        $journal = $service->resolveJournal($method, $company->id, $fiscalYear->id);
        if ($journal instanceof Journal && $this->journal_id === null) {
            $this->journal_id = $journal->id;
        }

        $destination = $service->resolveDestinationAccount($method, $company->id, $fiscalYear->id);
        if ($destination instanceof Account && $this->destination_account_id === null) {
            $this->destination_account_id = $destination->id;
        }
    }

    /** @return array{allocated: numeric-string, unallocated: numeric-string} */
    public function getTotalsProperty(): array
    {
        $service = app(SupplierPaymentService::class);

        $allocated = '0';
        foreach ($this->allocations as $row) {
            $value = $service->toDecimal($row['amount']);
            if (bccomp($value, '0', 3) > 0) {
                $allocated = bcadd($allocated, $value, 3);
            }
        }

        $unallocated = bcsub($service->toDecimal($this->amount), $allocated, 3);
        if (bccomp($unallocated, '0', 3) < 0) {
            $unallocated = '0.000';
        }

        return [
            'allocated' => $allocated,
            'unallocated' => $unallocated,
        ];
    }

    /**
     * @return array<int, array{purchase_invoice_id: int, amount: numeric-string}>
     */
    private function buildAllocationsData(): array
    {
        $service = app(SupplierPaymentService::class);

        $data = [];
        foreach ($this->allocations as $row) {
            $value = $service->toDecimal($row['amount']);
            if (bccomp($value, '0', 3) > 0) {
                $data[] = [
                    'purchase_invoice_id' => (int) $row['purchase_invoice_id'],
                    'amount' => $value,
                ];
            }
        }

        return $data;
    }

    public function save(
        bool $withPost,
        SupplierPaymentService $service,
        CurrentCompany $currentCompany,
        CurrentFiscalYear $currentFiscalYear,
        CurrentAccountingPeriod $currentAccountingPeriod,
    ): void {
        $this->saveAndPost = $withPost;

        $company = $currentCompany->get(Auth::user());
        $fiscalYear = $currentFiscalYear->get(Auth::user());
        $accountingPeriod = $currentAccountingPeriod->get(Auth::user());

        if (! $company || ! $fiscalYear || ! $accountingPeriod) {
            session()->flash('error', 'Contexte comptable incomplet. Veuillez sélectionner une société, un exercice et une période.');

            return;
        }

        if ($this->supplier_id === null) {
            $this->addError('supplier_id', 'Veuillez sélectionner un fournisseur.');

            return;
        }

        if (Auth::user()->cannot('create', [SupplierPayment::class, $company])) {
            abort(403);
        }

        $this->validate([
            'payment_date' => ['required', 'date'],
            'payment_method_id' => ['required', 'integer'],
            'journal_id' => ['required', 'integer'],
            'destination_account_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.001'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $payment = $service->createDraft([
                'company_id' => $company->id,
                'supplier_id' => $this->supplier_id,
                'fiscal_year_id' => $fiscalYear->id,
                'accounting_period_id' => $accountingPeriod->id,
                'payment_method_id' => (int) $this->payment_method_id,
                'journal_id' => (int) $this->journal_id,
                'destination_account_id' => (int) $this->destination_account_id,
                'payment_date' => $this->payment_date,
                'amount' => $this->amount,
                'currency' => $company->currency ?? 'TND',
                'reference' => $this->reference,
                'notes' => $this->notes,
                'created_by' => (int) Auth::id(),
                'allocations' => $this->buildAllocationsData(),
            ]);

            if ($this->saveAndPost) {
                $service->post($payment, (int) Auth::id());
            }
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $label = $this->saveAndPost ? 'créé et comptabilisé' : 'créé';
        session()->flash('success', "Le règlement « {$payment->payment_number} » a été {$label}.");
        $this->redirect(route('supplier-payments.show', $payment->id), navigate: true);
    }

    public function render(CurrentCompany $currentCompany): View
    {
        $company = $currentCompany->get(Auth::user());
        $fiscalYear = app(CurrentFiscalYear::class)->get(Auth::user());

        $suppliers = collect();
        $methods = collect();
        $journals = collect();
        $accounts = collect();

        if ($company) {
            $suppliers = Supplier::where('company_id', $company->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            $methods = PaymentMethod::where('company_id', $company->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            $journalsQuery = Journal::where('company_id', $company->id)
                ->where('is_active', true);

            $accountsQuery = Account::where('company_id', $company->id)
                ->where('is_active', true);

            if ($fiscalYear) {
                $journalsQuery->where('fiscal_year_id', $fiscalYear->id);
                $accountsQuery->where('fiscal_year_id', $fiscalYear->id);
            }

            $journals = $journalsQuery->orderBy('code')->get();
            $accounts = $accountsQuery->orderBy('code')->get();
        }

        return view('livewire.supplier-payments.create', [
            'currentCompany' => $company,
            'suppliers' => $suppliers,
            'methods' => $methods,
            'journals' => $journals,
            'accounts' => $accounts,
            'statuses' => [SupplierPaymentStatus::DRAFT],
        ]);
    }
}
