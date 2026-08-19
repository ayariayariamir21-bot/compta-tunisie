<?php

namespace App\Livewire\Journals;

use App\Enums\JournalType;
use App\Models\Journal;
use App\Services\Accounting\ChartOfJournalsSeeder;
use App\Services\Accounting\JournalService;
use App\Services\CurrentCompany;
use App\Services\CurrentFiscalYear;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Index extends Component
{
    public string $search = '';

    public string $filterType = '';

    public function delete(Journal $journal, JournalService $journalService): void
    {
        if (Auth::user()->cannot('delete', $journal)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        $currentFiscalYear = app(CurrentFiscalYear::class)->get(Auth::user());

        if (! $currentCompany || ! $currentFiscalYear) {
            abort(403);
        }

        if ($journal->company_id !== $currentCompany->id || $journal->fiscal_year_id !== $currentFiscalYear->id) {
            abort(403);
        }

        $journalService->deleteJournal($journal);

        session()->flash('success', "Le journal « {$journal->name} » a été supprimé.");

        $this->redirect(route('journals.index'), navigate: true);
    }

    public function toggleActive(Journal $journal, JournalService $journalService): void
    {
        if (Auth::user()->cannot('activate', $journal)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        $currentFiscalYear = app(CurrentFiscalYear::class)->get(Auth::user());

        if (! $currentCompany || ! $currentFiscalYear) {
            abort(403);
        }

        if ($journal->company_id !== $currentCompany->id || $journal->fiscal_year_id !== $currentFiscalYear->id) {
            abort(403);
        }

        $journalService->toggleActive($journal);

        $label = $journal->fresh()->is_active ? 'activé' : 'désactivé';
        session()->flash('success', "Le journal « {$journal->name} » a été {$label}.");

        $this->redirect(route('journals.index'), navigate: true);
    }

    public function initializeJournals(CurrentCompany $currentCompany, CurrentFiscalYear $currentFiscalYear, ChartOfJournalsSeeder $seeder): void
    {
        $company = $currentCompany->get(Auth::user());
        $fiscalYear = $currentFiscalYear->get(Auth::user());

        if (! $company || ! $fiscalYear) {
            session()->flash('error', 'Aucune société ou exercice sélectionné.');

            return;
        }

        if (Auth::user()->cannot('create', [Journal::class, $company])) {
            abort(403);
        }

        $count = $seeder->seed($company, $fiscalYear);

        if ($count > 0) {
            session()->flash('success', "{$count} journal(aux) initialisé(s) avec succès.");
        } else {
            session()->flash('success', 'Les journaux sont déjà initialisés.');
        }

        $this->redirect(route('journals.index'), navigate: true);
    }

    public function render(CurrentCompany $currentCompany, CurrentFiscalYear $currentFiscalYear): View
    {
        $company = $currentCompany->get(Auth::user());
        $fiscalYear = $currentFiscalYear->get(Auth::user());

        $journals = collect();

        if ($company && $fiscalYear) {
            $query = Journal::where('company_id', $company->id)
                ->where('fiscal_year_id', $fiscalYear->id);

            if ($this->search !== '') {
                $search = $this->search;
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%");
                });
            }

            if ($this->filterType !== '') {
                $query->where('type', $this->filterType);
            }

            $journals = $query->orderBy('code')->get();
        }

        return view('livewire.journals.index', [
            'journals' => $journals,
            'currentCompany' => $company,
            'currentFiscalYear' => $fiscalYear,
            'journalTypes' => JournalType::cases(),
        ]);
    }
}
