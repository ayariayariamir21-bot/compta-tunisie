<?php

namespace App\Livewire\SupplierPayments;

use App\Enums\PurchaseInvoiceStatus;
use App\Enums\SupplierPaymentStatus;
use App\Models\PurchaseInvoice;
use App\Models\SupplierPayment;
use App\Services\CurrentCompany;
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
#[Title('Modifier le règlement fournisseur')]
class Edit extends Component
{
    public int $supplierPaymentId;

    public string $paymentNumber = '';

    public ?int $supplier_id = null;

    public string $payment_date = '';

    public string $amount = '';

    public ?string $reference = null;

    public ?string $notes = null;

    public bool $saveAndPost = false;

    /**
     * Existing allocation rows first, then other open invoices of the supplier.
     *
     * @var array<int, array{purchase_invoice_id: int, number: string, date: string, total: string, paid: string, remaining: string, amount: string}>
     */
    public array $allocations = [];

    public function mount(int $supplierPaymentId): void
    {
        $company = app(CurrentCompany::class)->get(Auth::user());

        /** @var SupplierPayment|null $payment */
        $payment = SupplierPayment::where('id', $supplierPaymentId)
            ->when($company !== null, fn ($q) => $q->where('company_id', $company->id))
            ->with(['supplier'])
            ->first();

        if (! $payment) {
            abort(404);
        }

        if (Auth::user()->cannot('update', $payment)) {
            abort(403);
        }

        $this->supplierPaymentId = $payment->id;
        $this->paymentNumber = $payment->payment_number;
        $this->supplier_id = $payment->supplier_id;
        $this->payment_date = $payment->payment_date->toDateString();
        $this->amount = app(SupplierPaymentService::class)->toDecimal((string) $payment->amount);
        $this->reference = $payment->reference;
        $this->notes = $payment->notes;

        $this->loadSupplierInvoices();
    }

    private function loadSupplierInvoices(): void
    {
        $company = app(CurrentCompany::class)->get(Auth::user());
        $service = app(SupplierPaymentService::class);

        $this->allocations = [];

        if ($company === null || $this->supplier_id === null) {
            return;
        }

        /** @var SupplierPayment $payment */
        $payment = SupplierPayment::where('id', $this->supplierPaymentId)
            ->with(['allocations.purchaseInvoice'])
            ->firstOrFail();

        $existingByInvoiceId = [];
        foreach ($payment->allocations as $allocation) {
            $existingByInvoiceId[(int) $allocation->purchase_invoice_id] = $service->toDecimal((string) $allocation->amount);
        }

        $invoices = PurchaseInvoice::where('company_id', $company->id)
            ->where('supplier_id', $this->supplier_id)
            ->where('status', PurchaseInvoiceStatus::POSTED)
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->get();

        foreach ($invoices as $invoice) {
            $total = $service->toDecimal((string) $invoice->total);
            $paid = bcadd(
                $service->getPostedAllocatedAmount($invoice),
                $existingByInvoiceId[(int) $invoice->id] ?? '0',
                3
            );
            $remaining = bcsub($total, $paid, 3);

            $hasRow = array_key_exists((int) $invoice->id, $existingByInvoiceId);
            if (! $hasRow && bccomp($remaining, '0', 3) <= 0) {
                continue;
            }

            $displayRemaining = bccomp($remaining, '0', 3) < 0 ? '0.000' : $remaining;

            $this->allocations[] = [
                'purchase_invoice_id' => (int) $invoice->id,
                'number' => $invoice->invoice_number,
                'date' => $invoice->invoice_date->format('d/m/Y'),
                'total' => $total,
                'paid' => $paid,
                'remaining' => $displayRemaining,
                'amount' => $existingByInvoiceId[(int) $invoice->id] ?? '0.000',
            ];
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
        CurrentCompany $currentCompany,
        SupplierPaymentService $service,
    ): void {
        $this->saveAndPost = $withPost;

        $company = $currentCompany->get(Auth::user());

        /** @var SupplierPayment|null $payment */
        $payment = SupplierPayment::where('id', $this->supplierPaymentId)
            ->when($company !== null, fn ($q) => $q->where('company_id', $company->id))
            ->first();

        if (! $payment) {
            abort(404);
        }

        if (Auth::user()->cannot('update', $payment)) {
            abort(403);
        }

        $this->validate([
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.001'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $updated = $service->updateDraft($payment, [
                'payment_date' => $this->payment_date,
                'amount' => $this->amount,
                'reference' => $this->reference,
                'notes' => $this->notes,
                'allocations' => $this->buildAllocationsData(),
            ]);

            if ($this->saveAndPost) {
                $service->post($updated, (int) Auth::id());
            }
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $label = $this->saveAndPost ? 'modifié et comptabilisé' : 'modifié';
        session()->flash('success', "Le règlement « {$updated->payment_number} » a été {$label}.");
        $this->redirect(route('supplier-payments.show', $updated->id), navigate: true);
    }

    public function render(CurrentCompany $currentCompany): View
    {
        $company = $currentCompany->get(Auth::user());

        /** @var SupplierPayment|null $payment */
        $payment = SupplierPayment::where('id', $this->supplierPaymentId ?? 0)
            ->when($company !== null, fn ($q) => $q->where('company_id', $company->id))
            ->with([
                'supplier', 'paymentMethod', 'journal', 'destinationAccount',
                'fiscalYear', 'accountingPeriod',
            ])
            ->first();

        if (! $payment || $payment->status !== SupplierPaymentStatus::DRAFT) {
            abort(404);
        }

        return view('livewire.supplier-payments.edit', [
            'payment' => $payment,
            'currentCompany' => $company,
        ]);
    }
}
