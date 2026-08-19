<?php

namespace App\Livewire\Journals;

use App\Enums\JournalType;
use App\Models\Journal;
use App\Services\Accounting\JournalService;
use App\Services\CurrentCompany;
use App\Services\CurrentFiscalYear;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Edit extends Component
{
    public ?Journal $journal = null;

    public string $code = '';

    public string $name = '';

    public ?JournalType $type = null;

    public bool $is_active = true;

    public function mount(int $journalId, CurrentCompany $currentCompany, CurrentFiscalYear $currentFiscalYear): void
    {
        $journal = Journal::findOrFail($journalId);

        $company = $currentCompany->get(Auth::user());
        $fiscalYear = $currentFiscalYear->get(Auth::user());

        if (! $company || ! $fiscalYear) {
            abort(403);
        }

        if ($journal->company_id !== $company->id || $journal->fiscal_year_id !== $fiscalYear->id) {
            abort(403);
        }

        if (Auth::user()->cannot('update', $journal)) {
            abort(403);
        }

        $this->journal = $journal;
        $this->code = $journal->code;
        $this->name = $journal->name;
        $this->type = $journal->type;
        $this->is_active = $journal->is_active;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:10'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'enum:'.JournalType::class],
            'is_active' => ['boolean'],
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'code' => 'le code',
            'name' => 'le nom',
            'type' => 'le type',
            'is_active' => 'l\'état',
        ];
    }

    public function update(CurrentCompany $currentCompany, CurrentFiscalYear $currentFiscalYear, JournalService $journalService): void
    {
        $company = $currentCompany->get(Auth::user());
        $fiscalYear = $currentFiscalYear->get(Auth::user());

        if (! $company || ! $fiscalYear) {
            session()->flash('error', 'Aucune société ou exercice sélectionné.');

            return;
        }

        if ($this->journal->company_id !== $company->id || $this->journal->fiscal_year_id !== $fiscalYear->id) {
            abort(403);
        }

        if (Auth::user()->cannot('update', $this->journal)) {
            abort(403);
        }

        $validated = $this->validate();

        try {
            $journalService->updateJournal($this->journal, $validated);
        } catch (\InvalidArgumentException $e) {
            $this->addError('code', $e->getMessage());

            return;
        }

        session()->flash('success', "Le journal « {$this->name} » a été mis à jour.");

        $this->redirect(route('journals.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.journals.edit', [
            'journalTypes' => JournalType::cases(),
        ]);
    }
}
