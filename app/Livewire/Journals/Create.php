<?php

namespace App\Livewire\Journals;

use App\Enums\JournalType;
use App\Models\Journal;
use App\Services\Accounting\JournalService;
use App\Services\CurrentCompany;
use App\Services\CurrentFiscalYear;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Create extends Component
{
    public string $code = '';

    public string $name = '';

    public ?JournalType $type = null;

    public bool $is_active = true;

    /** @return array<string, list<string|ValidationRule>> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:10'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'enum:'.JournalType::class],
            'is_active' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function validationAttributes(): array
    {
        return [
            'code' => 'le code',
            'name' => 'le nom',
            'type' => 'le type',
            'is_active' => 'l\'état',
        ];
    }

    public function store(CurrentCompany $currentCompany, CurrentFiscalYear $currentFiscalYear, JournalService $journalService): void
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

        $validated = $this->validate();

        try {
            $journalService->createJournal($company, $fiscalYear, $validated);
        } catch (\InvalidArgumentException $e) {
            $this->addError('code', $e->getMessage());

            return;
        }

        session()->flash('success', "Le journal « {$this->name} » a été créé.");

        $this->redirect(route('journals.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.journals.create', [
            'journalTypes' => JournalType::cases(),
        ]);
    }
}
