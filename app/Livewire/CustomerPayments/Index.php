<?php

namespace App\Livewire\CustomerPayments;

use App\Enums\CustomerPaymentStatus;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\PaymentMethod;
use App\Services\CurrentCompany;
use App\Services\CustomerPaymentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Encaissements')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterStatus = '';

    public ?int $filterCustomerId = null;

    public ?int $filterPaymentMethodId = null;

    public string $filterDateFrom = '';

    public string $filterDateTo = '';

    public int $perPage = 15;

    public function delete(CustomerPayment $customerPayment): void
    {
        if (Auth::user()->cannot('delete', $customerPayment)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        if (! $currentCompany || $customerPayment->company_id !== $currentCompany->id) {
            abort(403);
        }

        try {
            app(CustomerPaymentService::class)->deleteDraft($customerPayment);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
            $this->redirect(route('customer-payments.index'), navigate: true);

            return;
        }

        session()->flash('success', "L'encaissement « {$customerPayment->payment_number} » a été supprimé.");
        $this->redirect(route('customer-payments.index'), navigate: true);
    }

    public function post(CustomerPaymentService $service, CustomerPayment $customerPayment): void
    {
        if (Auth::user()->cannot('post', $customerPayment)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        if (! $currentCompany || $customerPayment->company_id !== $currentCompany->id) {
            abort(403);
        }

        try {
            $service->post($customerPayment, (int) Auth::id());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
            $this->redirect(route('customer-payments.index'), navigate: true);

            return;
        }

        session()->flash('success', "L'encaissement « {$customerPayment->payment_number} » a été comptabilisé.");
        $this->redirect(route('customer-payments.index'), navigate: true);
    }

    public function cancel(CustomerPayment $customerPayment): void
    {
        if (Auth::user()->cannot('cancel', $customerPayment)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        if (! $currentCompany || $customerPayment->company_id !== $currentCompany->id) {
            abort(403);
        }

        try {
            app(CustomerPaymentService::class)->cancel($customerPayment);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
            $this->redirect(route('customer-payments.index'), navigate: true);

            return;
        }

        session()->flash('success', "L'encaissement « {$customerPayment->payment_number} » a été annulé.");
        $this->redirect(route('customer-payments.index'), navigate: true);
    }

    public function render(CurrentCompany $currentCompany): View
    {
        $company = $currentCompany->get(Auth::user());
        $payments = collect();
        $customers = collect();
        $paymentMethods = collect();

        if ($company) {
            $query = CustomerPayment::where('customer_payments.company_id', $company->id)
                ->with(['customer', 'paymentMethod', 'allocations']);

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

            if ($this->filterCustomerId !== null) {
                $query->where('customer_id', $this->filterCustomerId);
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

            $customers = Customer::where('company_id', $company->id)
                ->orderBy('name')
                ->get();

            $paymentMethods = PaymentMethod::where('company_id', $company->id)
                ->orderBy('sort_order')
                ->get();
        }

        return view('livewire.customer-payments.index', [
            'payments' => $payments,
            'currentCompany' => $company,
            'statuses' => CustomerPaymentStatus::cases(),
            'customers' => $customers,
            'paymentMethods' => $paymentMethods,
        ]);
    }
}
