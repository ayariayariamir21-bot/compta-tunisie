<?php

namespace App\Livewire\CustomerPayments;

use App\Models\CustomerPayment;
use App\Services\CurrentCompany;
use App\Services\CustomerPaymentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Détail de l\'encaissement')]
class Show extends Component
{
    public int $paymentId;

    public function mount(int $paymentId): void
    {
        $this->paymentId = $paymentId;
    }

    public function delete(CustomerPayment $customerPayment): void
    {
        if (Auth::user()->cannot('delete', $customerPayment)) {
            abort(403);
        }

        $company = app(CurrentCompany::class)->get(Auth::user());
        if (! $company || $customerPayment->company_id !== $company->id) {
            abort(403);
        }

        try {
            app(CustomerPaymentService::class)->deleteDraft($customerPayment);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

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

        $company = app(CurrentCompany::class)->get(Auth::user());
        if (! $company || $customerPayment->company_id !== $company->id) {
            abort(403);
        }

        try {
            $service->post($customerPayment, (int) Auth::id());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        session()->flash('success', "L'encaissement « {$customerPayment->payment_number} » a été comptabilisé.");

        $this->redirect(route('customer-payments.show', $customerPayment->id), navigate: true);
    }

    public function cancel(CustomerPayment $customerPayment): void
    {
        if (Auth::user()->cannot('cancel', $customerPayment)) {
            abort(403);
        }

        $company = app(CurrentCompany::class)->get(Auth::user());
        if (! $company || $customerPayment->company_id !== $company->id) {
            abort(403);
        }

        try {
            app(CustomerPaymentService::class)->cancel($customerPayment);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        session()->flash('success', "L'encaissement « {$customerPayment->payment_number} » a été annulé.");
        $this->redirect(route('customer-payments.show', $customerPayment->id), navigate: true);
    }

    public function render(CurrentCompany $currentCompany): View
    {
        $company = $currentCompany->get(Auth::user());

        if (! $company) {
            abort(403);
        }

        /** @var CustomerPayment|null $payment */
        $payment = CustomerPayment::where('id', $this->paymentId)
            ->where('company_id', $company->id)
            ->with([
                'customer', 'paymentMethod', 'journal', 'destinationAccount',
                'fiscalYear', 'accountingPeriod',
                'allocations.invoice', 'journalEntry.lines.account', 'creator',
            ])
            ->first();

        if (! $payment) {
            abort(404);
        }

        $unallocated = $payment->unallocatedAmount();

        return view('livewire.customer-payments.show', [
            'payment' => $payment,
            'currentCompany' => $company,
            'unallocated' => $unallocated,
        ]);
    }
}
