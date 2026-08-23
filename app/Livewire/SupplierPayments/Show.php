<?php

namespace App\Livewire\SupplierPayments;

use App\Models\SupplierPayment;
use App\Services\CurrentCompany;
use App\Services\SupplierPaymentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Détail du règlement fournisseur')]
class Show extends Component
{
    public int $supplierPaymentId;

    public function mount(int $supplierPaymentId): void
    {
        $this->supplierPaymentId = $supplierPaymentId;
    }

    public function delete(SupplierPayment $supplierPayment): void
    {
        if (Auth::user()->cannot('delete', $supplierPayment)) {
            abort(403);
        }

        $company = app(CurrentCompany::class)->get(Auth::user());
        if (! $company || $supplierPayment->company_id !== $company->id) {
            abort(403);
        }

        try {
            app(SupplierPaymentService::class)->deleteDraft($supplierPayment);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

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

        $company = app(CurrentCompany::class)->get(Auth::user());
        if (! $company || $supplierPayment->company_id !== $company->id) {
            abort(403);
        }

        try {
            $service->post($supplierPayment, (int) Auth::id());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        session()->flash('success', "Le règlement « {$supplierPayment->payment_number} » a été comptabilisé.");

        $this->redirect(route('supplier-payments.show', $supplierPayment->id), navigate: true);
    }

    public function cancel(SupplierPayment $supplierPayment): void
    {
        if (Auth::user()->cannot('cancel', $supplierPayment)) {
            abort(403);
        }

        $company = app(CurrentCompany::class)->get(Auth::user());
        if (! $company || $supplierPayment->company_id !== $company->id) {
            abort(403);
        }

        try {
            app(SupplierPaymentService::class)->cancel($supplierPayment);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        session()->flash('success', "Le règlement « {$supplierPayment->payment_number} » a été annulé.");
        $this->redirect(route('supplier-payments.show', $supplierPayment->id), navigate: true);
    }

    public function render(CurrentCompany $currentCompany): View
    {
        $company = $currentCompany->get(Auth::user());

        /** @var SupplierPayment|null $payment */
        $payment = SupplierPayment::where('id', $this->supplierPaymentId)
            ->when($company !== null, fn ($q) => $q->where('company_id', $company->id))
            ->with([
                'supplier', 'paymentMethod', 'journal', 'destinationAccount',
                'fiscalYear', 'accountingPeriod',
                'allocations.purchaseInvoice', 'journalEntry.lines.account', 'creator',
            ])
            ->first();

        if (! $payment) {
            abort(404);
        }

        $unallocated = $payment->unallocatedAmount();

        return view('livewire.supplier-payments.show', [
            'payment' => $payment,
            'currentCompany' => $company,
            'unallocated' => $unallocated,
        ]);
    }
}
