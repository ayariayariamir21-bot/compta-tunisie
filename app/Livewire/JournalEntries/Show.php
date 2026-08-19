<?php

namespace App\Livewire\JournalEntries;

use App\Models\JournalEntry;
use App\Services\CurrentCompany;
use App\Services\CurrentFiscalYear;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Show extends Component
{
    public ?JournalEntry $journalEntry = null;

    public function mount(int $journalEntryId, CurrentCompany $currentCompany, CurrentFiscalYear $currentFiscalYear): void
    {
        $entry = JournalEntry::with(['lines.account', 'journal', 'creator', 'accountingPeriod'])
            ->findOrFail($journalEntryId);

        $company = $currentCompany->get(Auth::user());
        $fiscalYear = $currentFiscalYear->get(Auth::user());

        if (! $company || ! $fiscalYear) {
            abort(403);
        }

        if ($entry->company_id !== $company->id || $entry->fiscal_year_id !== $fiscalYear->id) {
            abort(403);
        }

        if (Auth::user()->cannot('view', $entry)) {
            abort(403);
        }

        $this->journalEntry = $entry;
    }

    public function render(): View
    {
        return view('livewire.journal-entries.show', [
            'journalEntry' => $this->journalEntry,
        ]);
    }
}
