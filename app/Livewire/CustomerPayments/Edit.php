<?php

namespace App\Livewire\CustomerPayments;

use App\Enums\CustomerPaymentStatus;
use App\Enums\InvoiceStatus;
use App\Models\CustomerPayment;
use App\Models\Invoice;
use App\Services\CurrentCompany;
use App\Services\CustomerPaymentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read array{allocated: numeric-string, unallocated: numeric-string} $totals
 */
#[Layout('layouts.app')]
#[Title('Modifier l\'encaissement')]
class Edit extends Component
{
    public int $paymentId;

    public string $paymentNumber = '';

    public ?int $customer_id = null;

    public string $payment_date = '';

    public string $amount = '';

    public ?string $reference = null;

    public ?string $notes = null;

    public bool $saveAndPost = false;

    /**
     * Existing allocation rows first, then other open invoices of the customer.
     *
     * @var array<int, array{invoice_id: int, number: string, date: string, total: string, credited: string, paid: string, remaining: string, amount: string}>
     */
    public array $allocations = [];

    public function mount(int $paymentId): void
    {
        $company = app(CurrentCompany::class)->get(Auth::user());

        if (! $company) {
            abort(403);
        }

        /** @var CustomerPayment|null $payment */
        $payment = CustomerPayment::where('id', $paymentId)
            ->where('company_id', $company->id)
            ->with(['customer'])
            ->first();

        if (! $payment) {
            abort(404);
        }

        if (Auth::user()->cannot('update', $payment)) {
            abort(403);
        }

        $this->paymentId = $payment->id;
        $this->paymentNumber = $payment->payment_number;
        $this->customer_id = $payment->customer_id;
        $this->payment_date = $payment->payment_date->toDateString();
        $this->amount = app(CustomerPaymentService::class)->toDecimal((string) $payment->amount);
        $this->reference = $payment->reference;
        $this->notes = $payment->notes;

        $this->loadCustomerInvoices();
    }

    private function loadCustomerInvoices(): void
    {
        $company = app(CurrentCompany::class)->get(Auth::user());
        $service = app(CustomerPaymentService::class);

        $this->allocations = [];

        if ($company === null || $this->customer_id === null) {
            return;
        }

        /** @var CustomerPayment $payment */
        $payment = CustomerPayment::where('id', $this->paymentId)
            ->with(['allocations.invoice'])
            ->firstOrFail();

        $existingByInvoiceId = [];
        foreach ($payment->allocations as $allocation) {
            $existingByInvoiceId[(int) $allocation->invoice_id] = $service->toDecimal((string) $allocation->amount);
        }

        $invoices = Invoice::where('company_id', $company->id)
            ->where('customer_id', $this->customer_id)
            ->where('status', InvoiceStatus::POSTED)
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->get();

        foreach ($invoices as $invoice) {
            $total = $service->toDecimal((string) $invoice->total);
            $credited = $service->getPostedCreditNoteImpact($invoice);
            $paid = bcadd(
                $service->getPostedAllocatedAmount($invoice),
                $existingByInvoiceId[(int) $invoice->id] ?? '0',
                3
            );
            $remaining = bcsub(bcsub($total, $credited, 3), $paid, 3);

            $hasRow = array_key_exists((int) $invoice->id, $existingByInvoiceId);
            if (! $hasRow && bccomp($remaining, '0', 3) <= 0) {
                continue;
            }

            $displayRemaining = bccomp($remaining, '0', 3) < 0 ? '0.000' : $remaining;

            $this->allocations[] = [
                'invoice_id' => (int) $invoice->id,
                'number' => $invoice->invoice_number,
                'date' => $invoice->invoice_date->format('d/m/Y'),
                'total' => $total,
                'credited' => $credited,
                'paid' => $paid,
                'remaining' => $displayRemaining,
                'amount' => $existingByInvoiceId[(int) $invoice->id] ?? '0.000',
            ];
        }
    }

    /** @return array{allocated: numeric-string, unallocated: numeric-string} */
    public function getTotalsProperty(): array
    {
        $service = app(CustomerPaymentService::class);

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
     * @return array<int, array{invoice_id: int, amount: numeric-string}>
     */
    private function buildAllocationsData(): array
    {
        $service = app(CustomerPaymentService::class);

        $data = [];
        foreach ($this->allocations as $row) {
            $value = $service->toDecimal($row['amount']);
            if (bccomp($value, '0', 3) > 0) {
                $data[] = [
                    'invoice_id' => (int) $row['invoice_id'],
                    'amount' => $value,
                ];
            }
        }

        return $data;
    }

    public function save(
        bool $withPost,
        CurrentCompany $currentCompany,
        CustomerPaymentService $service,
    ): void {
        $this->saveAndPost = $withPost;

        $company = $currentCompany->get(Auth::user());

        if (! $company) {
            abort(403);
        }

        /** @var CustomerPayment|null $payment */
        $payment = CustomerPayment::where('id', $this->paymentId)
            ->where('company_id', $company->id)
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
        session()->flash('success', "L'encaissement « {$updated->payment_number} » a été {$label}.");
        $this->redirect(route('customer-payments.show', $updated->id), navigate: true);
    }

    public function render(CurrentCompany $currentCompany): View
    {
        $company = $currentCompany->get(Auth::user());

        if (! $company) {
            abort(403);
        }

        /** @var CustomerPayment|null $payment */
        $payment = CustomerPayment::where('id', $this->paymentId ?? 0)
            ->where('company_id', $company->id)
            ->with([
                'customer', 'paymentMethod', 'journal', 'destinationAccount',
                'fiscalYear', 'accountingPeriod',
            ])
            ->first();

        if (! $payment || $payment->status !== CustomerPaymentStatus::DRAFT) {
            abort(404);
        }

        return view('livewire.customer-payments.edit', [
            'payment' => $payment,
            'currentCompany' => $company,
        ]);
    }
}
