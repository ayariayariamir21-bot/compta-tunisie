<?php

namespace App\Livewire\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\CurrentCompany;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Factures')]
class Index extends Component
{
    public string $search = '';

    public string $filterStatus = '';

    public ?int $filterCustomerId = null;

    public string $filterDateFrom = '';

    public string $filterDateTo = '';

    public int $perPage = 15;

    public function delete(Invoice $invoice): void
    {
        if (Auth::user()->cannot('delete', $invoice)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        if (! $currentCompany || $invoice->company_id !== $currentCompany->id) {
            abort(403);
        }

        try {
            app(InvoiceService::class)->deleteDraft($invoice);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
            $this->redirect(route('invoices.index'), navigate: true);

            return;
        }

        session()->flash('success', "La facture « {$invoice->invoice_number} » a été supprimée.");
        $this->redirect(route('invoices.index'), navigate: true);
    }

    public function post(Invoice $invoice): void
    {
        if (Auth::user()->cannot('post', $invoice)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        if (! $currentCompany || $invoice->company_id !== $currentCompany->id) {
            abort(403);
        }

        try {
            app(InvoiceService::class)->post($invoice, (int) Auth::id());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
            $this->redirect(route('invoices.index'), navigate: true);

            return;
        }

        session()->flash('success', "La facture « {$invoice->invoice_number} » a été comptabilisée.");
        $this->redirect(route('invoices.index'), navigate: true);
    }

    public function cancel(Invoice $invoice): void
    {
        if (Auth::user()->cannot('cancel', $invoice)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        if (! $currentCompany || $invoice->company_id !== $currentCompany->id) {
            abort(403);
        }

        try {
            app(InvoiceService::class)->cancel($invoice);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
            $this->redirect(route('invoices.index'), navigate: true);

            return;
        }

        session()->flash('success', "La facture « {$invoice->invoice_number} » a été annulée.");
        $this->redirect(route('invoices.index'), navigate: true);
    }

    public function render(CurrentCompany $currentCompany): View
    {
        $company = $currentCompany->get(Auth::user());
        $invoices = collect();
        $customers = collect();

        if ($company) {
            $query = Invoice::where('invoices.company_id', $company->id)
                ->with('customer');

            if ($this->search !== '') {
                $search = $this->search;
                $query->where(function ($q) use ($search) {
                    $q->where('invoice_number', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            }

            if ($this->filterStatus !== '') {
                $query->where('status', $this->filterStatus);
            }

            if ($this->filterCustomerId !== null) {
                $query->where('customer_id', $this->filterCustomerId);
            }

            if ($this->filterDateFrom !== '') {
                $query->where('invoice_date', '>=', $this->filterDateFrom);
            }

            if ($this->filterDateTo !== '') {
                $query->where('invoice_date', '<=', $this->filterDateTo);
            }

            $invoices = $query->orderByDesc('invoice_date')
                ->orderByDesc('id')
                ->paginate($this->perPage);

            $customers = Customer::where('company_id', $company->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        return view('livewire.invoices.index', [
            'invoices' => $invoices,
            'currentCompany' => $company,
            'invoiceStatuses' => InvoiceStatus::cases(),
            'customers' => $customers,
        ]);
    }
}
