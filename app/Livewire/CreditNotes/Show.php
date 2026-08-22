<?php

namespace App\Livewire\CreditNotes;

use App\Models\CreditNote;
use App\Services\CreditNoteService;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Avoir')]
class Show extends Component
{
    public CreditNote $creditNote;

    public function mount(int $creditNoteId): void
    {
        $currentCompany = app(CurrentCompany::class)->get(Auth::user());

        $this->creditNote = CreditNote::with([
            'lines.product', 'lines.taxRate', 'lines.salesAccount', 'lines.invoiceLine',
            'customer', 'invoice', 'fiscalYear', 'accountingPeriod', 'journal',
            'journalEntry.lines.account', 'creator',
        ])->findOrFail($creditNoteId);

        if (! $currentCompany || $this->creditNote->company_id !== $currentCompany->id) {
            abort(404);
        }
    }

    public function post(CreditNoteService $creditNoteService): void
    {
        if (Auth::user()->cannot('post', $this->creditNote)) {
            abort(403);
        }

        try {
            $this->creditNote = $creditNoteService->post($this->creditNote, (int) Auth::id());
            session()->flash('success', "L'avoir « {$this->creditNote->credit_note_number} » a été comptabilisé.");
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->redirect(route('credit-notes.show', $this->creditNote->id), navigate: true);
    }

    public function cancel(CreditNoteService $creditNoteService): void
    {
        if (Auth::user()->cannot('cancel', $this->creditNote)) {
            abort(403);
        }

        try {
            $this->creditNote = $creditNoteService->cancel($this->creditNote);
            session()->flash('success', "L'avoir « {$this->creditNote->credit_note_number} » a été annulé.");
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->redirect(route('credit-notes.show', $this->creditNote->id), navigate: true);
    }

    public function delete(CreditNoteService $creditNoteService): void
    {
        if (Auth::user()->cannot('delete', $this->creditNote)) {
            abort(403);
        }

        try {
            $creditNoteService->deleteDraft($this->creditNote);
            session()->flash('success', "L'avoir « {$this->creditNote->credit_note_number} » a été supprimé.");
            $this->redirect(route('credit-notes.index'), navigate: true);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
            $this->redirect(route('credit-notes.show', $this->creditNote->id), navigate: true);
        }
    }

    public function render(): View
    {
        return view('livewire.credit-notes.show');
    }
}
