<?php

namespace App\Livewire\SupplierPayments;

use App\Enums\SupplierPaymentStatus;
use App\Models\PaymentMethod;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\CurrentCompany;
use App\Services\SupplierPaymentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Règlements fournisseurs')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterStatus = '';

    public ?int $filterSupplierId = null;

    public ?int $filterPaymentMethodId = null;

    public string $filterDateFrom = '';

    public string $filterDateTo = '';

    public int $perPage = 15;

    public function delete(SupplierPayment $supplierPayment): void
    {
        if (Auth::user()->cannot('delete', $supplierPayment)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        if (! $currentCompany || $supplierPayment->company_id !== $currentCompany->id) {
            abort(403);
        }

        try {
            app(SupplierPaymentService::class)->deleteDraft($supplierPayment);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
            $this->redirect(route('supplier-payments.index'), navigate: true);

            return;
        }

        session()->flash('success', "Le règlement « {$supplierPayment->payment_number} » a été supprimé.");
        $this->redirect(route('supplier-payments.index'), navigate: true);
    }

    public function post(SupplierPaymentService $service, SupplierPayment $supplierPayment): void
    {
        if (Auth::user()->cannot('post', $supplierPayment)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        if (! $currentCompany || $supplierPayment->company_id !== $currentCompany->id) {
            abort(403);
        }

        try {
            $service->post($supplierPayment, (int) Auth::id());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
            $this->redirect(route('supplier-payments.index'), navigate: true);

            return;
        }

        session()->flash('success', "Le règlement « {$supplierPayment->payment_number} » a été comptabilisé.");
        $this->redirect(route('supplier-payments.index'), navigate: true);
    }

    public function cancel(SupplierPayment $supplierPayment): void
    {
        if (Auth::user()->cannot('cancel', $supplierPayment)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        if (! $currentCompany || $supplierPayment->company_id !== $currentCompany->id) {
            abort(403);
        }

        try {
            app(SupplierPaymentService::class)->cancel($supplierPayment);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
            $this->redirect(route('supplier-payments.index'), navigate: true);

            return;
        }

        session()->flash('success', "Le règlement « {$supplierPayment->payment_number} » a été annulé.");
        $this->redirect(route('supplier-payments.index'), navigate: true);
    }

    public function render(CurrentCompany $currentCompany): View
    {
        $company = $currentCompany->get(Auth::user());
        $payments = collect();
        $suppliers = collect();
        $paymentMethods = collect();

        if ($company) {
            $query = SupplierPayment::where('supplier_payments.company_id', $company->id)
                ->with(['supplier', 'paymentMethod', 'allocations']);

            if ($this->search !== '') {
                $search = $this->search;
                $query->where(function ($q) use ($search) {
                    $q->where('payment_number', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            }

            if ($this->filterStatus !== '') {
                $query->where('status', $this->filterStatus);
            }

            if ($this->filterSupplierId !== null) {
                $query->where('supplier_id', $this->filterSupplierId);
            }

            if ($this->filterPaymentMethodId !== null) {
                $query->where('payment_method_id', $this->filterPaymentMethodId);
            }

            if ($this->filterDateFrom !== '') {
                $query->where('payment_date', '>=', $this->filterDateFrom);
            }

            if ($this->filterDateTo !== '') {
                $query->where('payment_date', '<=', $this->filterDateTo);
            }

            $payments = $query->orderByDesc('payment_date')
                ->orderByDesc('id')
                ->paginate($this->perPage);

            $suppliers = Supplier::where('company_id', $company->id)
                ->orderBy('name')
                ->get();

            $paymentMethods = PaymentMethod::where('company_id', $company->id)
                ->orderBy('sort_order')
                ->get();
        }

        return view('livewire.supplier-payments.index', [
            'payments' => $payments,
            'currentCompany' => $company,
            'statuses' => SupplierPaymentStatus::cases(),
            'suppliers' => $suppliers,
            'paymentMethods' => $paymentMethods,
        ]);
    }
}
