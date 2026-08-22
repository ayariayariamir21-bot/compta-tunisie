<?php

namespace App\Livewire\CreditNotes;

use App\Enums\CreditNoteStatus;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\CreditNoteService;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Avoirs')]
class Index extends Component
{
    public string $search = '';

    public string $filterStatus = '';

    public ?int $filterCustomerId = null;

    public ?int $filterInvoiceId = null;

    public string $filterDateFrom = '';

    public string $filterDateTo = '';

    public int $perPage = 15;

    public function delete(CreditNote $creditNote): void
    {
        if (Auth::user()->cannot('delete', $creditNote)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        if (! $currentCompany || $creditNote->company_id !== $currentCompany->id) {
            abort(403);
        }

        try {
            app(CreditNoteService::class)->deleteDraft($creditNote);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
            $this->redirect(route('credit-notes.index'), navigate: true);

            return;
        }

        session()->flash('success', "L'avoir « {$creditNote->credit_note_number} » a été supprimé.");
        $this->redirect(route('credit-notes.index'), navigate: true);
    }

    public function post(CreditNoteService $creditNoteService, CreditNote $creditNote): void
    {
        if (Auth::user()->cannot('post', $creditNote)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        if (! $currentCompany || $creditNote->company_id !== $currentCompany->id) {
            abort(403);
        }

        try {
            $creditNoteService->post($creditNote, (int) Auth::id());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
            $this->redirect(route('credit-notes.index'), navigate: true);

            return;
        }

        session()->flash('success', "L'avoir « {$creditNote->credit_note_number} » a été comptabilisé.");
        $this->redirect(route('credit-notes.index'), navigate: true);
    }

    public function cancel(CreditNote $creditNote): void
    {
        if (Auth::user()->cannot('cancel', $creditNote)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        if (! $currentCompany || $creditNote->company_id !== $currentCompany->id) {
            abort(403);
        }

        try {
            app(CreditNoteService::class)->cancel($creditNote);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
            $this->redirect(route('credit-notes.index'), navigate: true);

            return;
        }

        session()->flash('success', "L'avoir « {$creditNote->credit_note_number} » a été annulé.");
        $this->redirect(route('credit-notes.index'), navigate: true);
    }

    public function render(CurrentCompany $currentCompany): View
    {
        $company = $currentCompany->get(Auth::user());
        $creditNotes = collect();
        $customers = collect();
        $invoices = collect();

        if ($company) {
            $query = CreditNote::where('credit_notes.company_id', $company->id)
                ->with(['customer', 'invoice']);

            if ($this->search !== '') {
                $search = $this->search;
                $query->where(function ($q) use ($search) {
                    $q->where('credit_note_number', 'like', "%{$search}%")
                        ->orWhere('reason', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            }

            if ($this->filterStatus !== '') {
                $query->where('status', $this->filterStatus);
            }

            if ($this->filterCustomerId !== null) {
                $query->where('customer_id', $this->filterCustomerId);
            }

            if ($this->filterInvoiceId !== null) {
                $query->where('invoice_id', $this->filterInvoiceId);
            }

            if ($this->filterDateFrom !== '') {
                $query->where('credit_note_date', '>=', $this->filterDateFrom);
            }

            if ($this->filterDateTo !== '') {
                $query->where('credit_note_date', '<=', $this->filterDateTo);
            }

            $creditNotes = $query->orderByDesc('credit_note_date')
                ->orderByDesc('id')
                ->paginate($this->perPage);

            $customers = Customer::where('company_id', $company->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            $invoices = Invoice::where('company_id', $company->id)
                ->where('status', 'posted')
                ->orderByDesc('invoice_date')
                ->get();
        }

        return view('livewire.credit-notes.index', [
            'creditNotes' => $creditNotes,
            'currentCompany' => $company,
            'creditNoteStatuses' => CreditNoteStatus::cases(),
            'customers' => $customers,
            'invoices' => $invoices,
        ]);
    }
}
