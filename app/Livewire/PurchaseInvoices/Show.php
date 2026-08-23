<?php

namespace App\Livewire\PurchaseInvoices;

use App\Enums\PurchaseInvoiceStatus;
use App\Models\PurchaseInvoice;
use App\Services\CurrentCompany;
use App\Services\PurchaseInvoiceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Facture fournisseur')]
class Show extends Component
{
    public ?PurchaseInvoice $purchaseInvoice = null;

    public function mount(int $purchaseInvoiceId): void
    {
        $this->purchaseInvoice = PurchaseInvoice::with([
            'lines.product', 'lines.taxRate', 'lines.purchaseAccount', 'supplier', 'fiscalYear', 'accountingPeriod', 'journal', 'journalEntry.lines.account', 'creator',
        ])->findOrFail($purchaseInvoiceId);

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        if (! $currentCompany || $this->purchaseInvoice->company_id !== $currentCompany->id) {
            abort(404);
        }
    }

    public function post(PurchaseInvoiceService $purchaseInvoiceService): void
    {
        if (Auth::user()->cannot('post', $this->purchaseInvoice)) {
            abort(403);
        }

        try {
            $this->purchaseInvoice = $purchaseInvoiceService->post($this->purchaseInvoice, (int) Auth::id());
            session()->flash('success', "La facture fournisseur « {$this->purchaseInvoice->invoice_number} » a été comptabilisée.");
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->redirect(route('purchase-invoices.show', $this->purchaseInvoice->id), navigate: true);
    }

    public function cancel(PurchaseInvoiceService $purchaseInvoiceService): void
    {
        if (Auth::user()->cannot('cancel', $this->purchaseInvoice)) {
            abort(403);
        }

        try {
            $this->purchaseInvoice = $purchaseInvoiceService->cancel($this->purchaseInvoice);
            session()->flash('success', "La facture fournisseur « {$this->purchaseInvoice->invoice_number} » a été annulée.");
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->redirect(route('purchase-invoices.show', $this->purchaseInvoice->id), navigate: true);
    }

    public function delete(PurchaseInvoiceService $purchaseInvoiceService): void
    {
        if (Auth::user()->cannot('delete', $this->purchaseInvoice)) {
            abort(403);
        }

        try {
            $invoiceNumber = $this->purchaseInvoice->invoice_number;
            $purchaseInvoiceService->deleteDraft($this->purchaseInvoice);
            session()->flash('success', "La facture fournisseur « {$invoiceNumber} » a été supprimée.");
            $this->redirect(route('purchase-invoices.index'), navigate: true);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
            $this->redirect(route('purchase-invoices.show', $this->purchaseInvoice->id), navigate: true);
        }
    }

    public function getIsDraftProperty(): bool
    {
        return $this->purchaseInvoice?->status === PurchaseInvoiceStatus::DRAFT;
    }

    public function getIsPostedProperty(): bool
    {
        return $this->purchaseInvoice?->status === PurchaseInvoiceStatus::POSTED;
    }

    public function render(): View
    {
        return view('livewire.purchase-invoices.show');
    }
}
