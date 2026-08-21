<?php

namespace App\Livewire\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\CurrentCompany;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Facture')]
class Show extends Component
{
    public ?Invoice $invoice = null;

    public function mount(int $id): void
    {
        $this->invoice = Invoice::with([
            'lines.product', 'lines.taxRate', 'lines.salesAccount', 'customer', 'fiscalYear', 'accountingPeriod', 'journal', 'journalEntry.lines.account', 'creator',
        ])->findOrFail($id);

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        if (! $currentCompany || $this->invoice->company_id !== $currentCompany->id) {
            abort(404);
        }
    }

    public function post(InvoiceService $invoiceService): void
    {
        if (Auth::user()->cannot('post', $this->invoice)) {
            abort(403);
        }

        try {
            $this->invoice = $invoiceService->post($this->invoice, (int) Auth::id());
            session()->flash('success', "La facture « {$this->invoice->invoice_number} » a été comptabilisée.");
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->redirect(route('invoices.show', $this->invoice->id), navigate: true);
    }

    public function cancel(InvoiceService $invoiceService): void
    {
        if (Auth::user()->cannot('cancel', $this->invoice)) {
            abort(403);
        }

        try {
            $this->invoice = $invoiceService->cancel($this->invoice);
            session()->flash('success', "La facture « {$this->invoice->invoice_number} » a été annulée.");
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->redirect(route('invoices.show', $this->invoice->id), navigate: true);
    }

    public function delete(InvoiceService $invoiceService): void
    {
        if (Auth::user()->cannot('delete', $this->invoice)) {
            abort(403);
        }

        try {
            $invoiceNumber = $this->invoice->invoice_number;
            $invoiceService->deleteDraft($this->invoice);
            session()->flash('success', "La facture « {$invoiceNumber} » a été supprimée.");
            $this->redirect(route('invoices.index'), navigate: true);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
            $this->redirect(route('invoices.show', $this->invoice->id), navigate: true);
        }
    }

    public function getIsDraftProperty(): bool
    {
        return $this->invoice?->status === InvoiceStatus::DRAFT;
    }

    public function getIsPostedProperty(): bool
    {
        return $this->invoice?->status === InvoiceStatus::POSTED;
    }

    public function render(): View
    {
        return view('livewire.invoices.show');
    }
}
