<?php

namespace App\Livewire\JournalEntries;

use App\Enums\JournalEntryStatus;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Services\Accounting\JournalEntryService;
use App\Services\CurrentCompany;
use App\Services\CurrentFiscalYear;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $filterJournal = null;

    public string $filterStatus = '';

    public function delete(JournalEntry $journalEntry, JournalEntryService $service): void
    {
        if (Auth::user()->cannot('delete', $journalEntry)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        $currentFiscalYear = app(CurrentFiscalYear::class)->get(Auth::user());

        if (! $currentCompany || ! $currentFiscalYear) {
            abort(403);
        }

        if ($journalEntry->company_id !== $currentCompany->id || $journalEntry->fiscal_year_id !== $currentFiscalYear->id) {
            abort(403);
        }

        $service->deleteDraft($journalEntry);

        session()->flash('success', "L'écriture « {$journalEntry->entry_number} » a été supprimée.");

        $this->redirect(route('journal-entries.index'), navigate: true);
    }

    public function post(JournalEntry $journalEntry, JournalEntryService $service): void
    {
        if (Auth::user()->cannot('post', $journalEntry)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        $currentFiscalYear = app(CurrentFiscalYear::class)->get(Auth::user());

        if (! $currentCompany || ! $currentFiscalYear) {
            abort(403);
        }

        if ($journalEntry->company_id !== $currentCompany->id || $journalEntry->fiscal_year_id !== $currentFiscalYear->id) {
            abort(403);
        }

        try {
            $service->post($journalEntry);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            $this->redirect(route('journal-entries.index'), navigate: true);

            return;
        }

        session()->flash('success', "L'écriture « {$journalEntry->entry_number} » a été comptabilisée.");

        $this->redirect(route('journal-entries.index'), navigate: true);
    }

    public function cancel(JournalEntry $journalEntry, JournalEntryService $service): void
    {
        if (Auth::user()->cannot('cancel', $journalEntry)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        $currentFiscalYear = app(CurrentFiscalYear::class)->get(Auth::user());

        if (! $currentCompany || ! $currentFiscalYear) {
            abort(403);
        }

        if ($journalEntry->company_id !== $currentCompany->id || $journalEntry->fiscal_year_id !== $currentFiscalYear->id) {
            abort(403);
        }

        $service->cancel($journalEntry);

        session()->flash('success', "L'écriture « {$journalEntry->entry_number} » a été annulée.");

        $this->redirect(route('journal-entries.index'), navigate: true);
    }

    public function render(CurrentCompany $currentCompany, CurrentFiscalYear $currentFiscalYear)
    {
        $company = $currentCompany->get(Auth::user());
        $fiscalYear = $currentFiscalYear->get(Auth::user());

        $entries = collect();

        if ($company && $fiscalYear) {
            $query = JournalEntry::where('journal_entries.company_id', $company->id)
                ->where('journal_entries.fiscal_year_id', $fiscalYear->id)
                ->with(['journal', 'creator'])
                ->withSum('lines', 'debit')
                ->withSum('lines', 'credit');

            if ($this->search !== '') {
                $search = $this->search;
                $query->where(function ($q) use ($search) {
                    $q->where('entry_number', 'ilike', "%{$search}%")
                        ->orWhere('reference', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%");
                });
            }

            if ($this->filterJournal !== null) {
                $query->where('journal_id', $this->filterJournal);
            }

            if ($this->filterStatus !== '') {
                $query->where('status', $this->filterStatus);
            }

            $entries = $query->orderByDesc('entry_date')
                ->orderByDesc('entry_number')
                ->paginate(15);
        }

        $journals = $company && $fiscalYear
            ? Journal::where('company_id', $company->id)
                ->where('fiscal_year_id', $fiscalYear->id)
                ->orderBy('code')
                ->get()
            : collect();

        return view('livewire.journal-entries.index', [
            'entries' => $entries,
            'currentCompany' => $company,
            'currentFiscalYear' => $fiscalYear,
            'journals' => $journals,
            'statuses' => JournalEntryStatus::cases(),
        ]);
    }
}
