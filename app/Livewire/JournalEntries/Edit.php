<?php

namespace App\Livewire\JournalEntries;

use App\Models\Account;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Services\Accounting\JournalEntryService;
use App\Services\CurrentCompany;
use App\Services\CurrentFiscalYear;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Edit extends Component
{
    public ?JournalEntry $journalEntry = null;

    public ?int $journal_id = null;

    public string $entry_date = '';

    public string $reference = '';

    public string $description = '';

    /** @var array<int, array{account_id: ?int, description: string, debit: string, credit: string}> */
    public array $lines = [];

    public function mount(int $journalEntryId, CurrentCompany $currentCompany, CurrentFiscalYear $currentFiscalYear): void
    {
        $entry = JournalEntry::with('lines')->findOrFail($journalEntryId);

        $company = $currentCompany->get(Auth::user());
        $fiscalYear = $currentFiscalYear->get(Auth::user());

        if (! $company || ! $fiscalYear) {
            abort(403);
        }

        if ($entry->company_id !== $company->id || $entry->fiscal_year_id !== $fiscalYear->id) {
            abort(403);
        }

        if (Auth::user()->cannot('update', $entry)) {
            abort(403);
        }

        $this->journalEntry = $entry;
        $this->journal_id = $entry->journal_id;
        $this->entry_date = Carbon::parse($entry->entry_date)->format('Y-m-d');
        $this->reference = $entry->reference ?? '';
        $this->description = $entry->description ?? '';
        $this->lines = $entry->lines->map(fn ($line) => [
            'account_id' => $line->account_id,
            'description' => $line->description ?? '',
            'debit' => $line->debit == '0.000' ? '' : (string) $line->debit,
            'credit' => $line->credit == '0.000' ? '' : (string) $line->credit,
        ])->toArray();
    }

    /** @return array<string, list<string|ValidationRule>> */
    public function rules(): array
    {
        return [
            'journal_id' => ['required', 'integer'],
            'entry_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.account_id' => ['required', 'integer'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.debit' => ['required', 'string'],
            'lines.*.credit' => ['required', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function validationAttributes(): array
    {
        return [
            'journal_id' => 'le journal',
            'entry_date' => 'la date',
            'reference' => 'la référence',
            'description' => 'la description',
            'lines' => 'les lignes',
        ];
    }

    public function addLine(): void
    {
        $this->lines[] = [
            'account_id' => null,
            'description' => '',
            'debit' => '',
            'credit' => '',
        ];
    }

    public function removeLine(int $index): void
    {
        if (count($this->lines) > 1) {
            unset($this->lines[$index]);
            $this->lines = array_values($this->lines);
        }
    }

    /** @return numeric-string */
    public function getTotalDebit(): string
    {
        $total = '0.000';
        foreach ($this->lines as $line) {
            $debit = $line['debit'] !== '' ? $line['debit'] : '0';
            $total = bcadd($total, is_numeric($debit) ? number_format((float) $debit, 3, '.', '') : '0.000', 3);
        }

        return $total;
    }

    /** @return numeric-string */
    public function getTotalCredit(): string
    {
        $total = '0.000';
        foreach ($this->lines as $line) {
            $credit = $line['credit'] !== '' ? $line['credit'] : '0';
            $total = bcadd($total, is_numeric($credit) ? number_format((float) $credit, 3, '.', '') : '0.000', 3);
        }

        return $total;
    }

    public function isBalanced(): bool
    {
        return bccomp($this->getTotalDebit(), $this->getTotalCredit(), 3) === 0;
    }

    public function update(CurrentCompany $currentCompany, CurrentFiscalYear $currentFiscalYear, JournalEntryService $service): void
    {
        $company = $currentCompany->get(Auth::user());
        $fiscalYear = $currentFiscalYear->get(Auth::user());

        if (! $company || ! $fiscalYear) {
            session()->flash('error', 'Aucune société ou exercice sélectionné.');

            return;
        }

        if ($this->journalEntry->company_id !== $company->id || $this->journalEntry->fiscal_year_id !== $fiscalYear->id) {
            abort(403);
        }

        if (Auth::user()->cannot('update', $this->journalEntry)) {
            abort(403);
        }

        $validated = $this->validate();

        /** @var list<array{account_id: int, description: string|null, debit: string, credit: string}> */
        $linesData = array_map(fn ($line) => [
            'account_id' => (int) $line['account_id'],
            'description' => $line['description'] ?? null,
            'debit' => $line['debit'] !== '' ? (string) $line['debit'] : '0',
            'credit' => $line['credit'] !== '' ? (string) $line['credit'] : '0',
        ], $validated['lines']);

        try {
            $service->updateDraft($this->journalEntry, $validated, $linesData);
        } catch (\InvalidArgumentException $e) {
            $this->addError('journal_id', $e->getMessage());

            return;
        }

        session()->flash('success', "L'écriture « {$this->journalEntry->entry_number} » a été mise à jour.");

        $this->redirect(route('journal-entries.index'), navigate: true);
    }

    public function render(CurrentCompany $currentCompany, CurrentFiscalYear $currentFiscalYear): View
    {
        $company = $currentCompany->get(Auth::user());
        $fiscalYear = $currentFiscalYear->get(Auth::user());

        $journals = collect();
        $accounts = collect();

        if ($company && $fiscalYear) {
            $journals = Journal::where('company_id', $company->id)
                ->where('fiscal_year_id', $fiscalYear->id)
                ->where('is_active', true)
                ->orderBy('code')
                ->get();

            $accounts = Account::where('company_id', $company->id)
                ->where('fiscal_year_id', $fiscalYear->id)
                ->where('is_active', true)
                ->orderBy('code')
                ->get();
        }

        return view('livewire.journal-entries.edit', [
            'journals' => $journals,
            'accounts' => $accounts,
        ]);
    }
}
