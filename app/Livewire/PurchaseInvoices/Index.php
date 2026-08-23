<?php

namespace App\Livewire\PurchaseInvoices;

use App\Enums\PurchaseInvoiceStatus;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Services\CurrentCompany;
use App\Services\PurchaseInvoiceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Factures fournisseurs')]
class Index extends Component
{
    public string $search = '';

    public string $filterStatus = '';

    public ?int $filterSupplierId = null;

    public string $filterDateFrom = '';

    public string $filterDateTo = '';

    public int $perPage = 15;

    public function delete(PurchaseInvoice $purchaseInvoice): void
    {
        if (Auth::user()->cannot('delete', $purchaseInvoice)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        if (! $currentCompany || $purchaseInvoice->company_id !== $currentCompany->id) {
            abort(403);
        }

        try {
            app(PurchaseInvoiceService::class)->deleteDraft($purchaseInvoice);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
            $this->redirect(route('purchase-invoices.index'), navigate: true);

            return;
        }

        session()->flash('success', "La facture fournisseur « {$purchaseInvoice->invoice_number} » a été supprimée.");
        $this->redirect(route('purchase-invoices.index'), navigate: true);
    }

    public function post(PurchaseInvoice $purchaseInvoice): void
    {
        if (Auth::user()->cannot('post', $purchaseInvoice)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        if (! $currentCompany || $purchaseInvoice->company_id !== $currentCompany->id) {
            abort(403);
        }

        try {
            app(PurchaseInvoiceService::class)->post($purchaseInvoice, (int) Auth::id());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
            $this->redirect(route('purchase-invoices.index'), navigate: true);

            return;
        }

        session()->flash('success', "La facture fournisseur « {$purchaseInvoice->invoice_number} » a été comptabilisée.");
        $this->redirect(route('purchase-invoices.index'), navigate: true);
    }

    public function cancel(PurchaseInvoice $purchaseInvoice): void
    {
        if (Auth::user()->cannot('cancel', $purchaseInvoice)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        if (! $currentCompany || $purchaseInvoice->company_id !== $currentCompany->id) {
            abort(403);
        }

        try {
            app(PurchaseInvoiceService::class)->cancel($purchaseInvoice);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
            $this->redirect(route('purchase-invoices.index'), navigate: true);

            return;
        }

        session()->flash('success', "La facture fournisseur « {$purchaseInvoice->invoice_number} » a été annulée.");
        $this->redirect(route('purchase-invoices.index'), navigate: true);
    }

    public function render(CurrentCompany $currentCompany): View
    {
        $company = $currentCompany->get(Auth::user());
        $purchaseInvoices = collect();
        $suppliers = collect();

        if ($company) {
            $query = PurchaseInvoice::where('purchase_invoices.company_id', $company->id)
                ->with('supplier');

            if ($this->search !== '') {
                $search = $this->search;
                $query->where(function ($q) use ($search) {
                    $q->where('invoice_number', 'like', "%{$search}%")
                        ->orWhere('supplier_invoice_number', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            }

            if ($this->filterStatus !== '') {
                $query->where('status', $this->filterStatus);
            }

            if ($this->filterSupplierId !== null) {
                $query->where('supplier_id', $this->filterSupplierId);
            }

            if ($this->filterDateFrom !== '') {
                $query->where('invoice_date', '>=', $this->filterDateFrom);
            }

            if ($this->filterDateTo !== '') {
                $query->where('invoice_date', '<=', $this->filterDateTo);
            }

            $purchaseInvoices = $query->orderByDesc('invoice_date')
                ->orderByDesc('id')
                ->paginate($this->perPage);

            $suppliers = Supplier::where('company_id', $company->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        return view('livewire.purchase-invoices.index', [
            'purchaseInvoices' => $purchaseInvoices,
            'currentCompany' => $company,
            'purchaseInvoiceStatuses' => PurchaseInvoiceStatus::cases(),
            'suppliers' => $suppliers,
        ]);
    }
}
