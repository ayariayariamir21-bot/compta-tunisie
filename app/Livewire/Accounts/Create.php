<?php

namespace App\Livewire\Accounts;

use App\Enums\AccountType;
use App\Models\Account;
use App\Services\Accounting\AccountService;
use App\Services\CurrentCompany;
use App\Services\CurrentFiscalYear;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Create extends Component
{
    public string $code = '';

    public string $name = '';

    public ?AccountType $account_type = null;

    public ?int $parent_id = null;

    public string $description = '';

    public bool $is_active = true;

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'account_type' => ['required', 'enum:'.AccountType::class],
            'parent_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'code' => 'le code',
            'name' => 'le nom',
            'account_type' => 'le type',
            'parent_id' => 'le compte parent',
            'description' => 'la description',
            'is_active' => 'l\'état',
        ];
    }

    public function store(CurrentCompany $currentCompany, CurrentFiscalYear $currentFiscalYear, AccountService $accountService): void
    {
        $company = $currentCompany->get(Auth::user());
        $fiscalYear = $currentFiscalYear->get(Auth::user());

        if (! $company || ! $fiscalYear) {
            session()->flash('error', 'Aucune société ou exercice sélectionné.');

            return;
        }

        if (Auth::user()->cannot('create', [Account::class, $company])) {
            abort(403);
        }

        $validated = $this->validate();

        try {
            $accountService->createAccount($company, $fiscalYear, $validated);
        } catch (\InvalidArgumentException $e) {
            $this->addError('code', $e->getMessage());

            return;
        }

        session()->flash('success', "Le compte « {$this->name} » a été créé.");

        $this->redirect(route('accounts.index'), navigate: true);
    }

    public function render(CurrentCompany $currentCompany, CurrentFiscalYear $currentFiscalYear)
    {
        $company = $currentCompany->get(Auth::user());
        $fiscalYear = $currentFiscalYear->get(Auth::user());

        $parents = collect();

        if ($company && $fiscalYear) {
            $parents = Account::where('company_id', $company->id)
                ->where('fiscal_year_id', $fiscalYear->id)
                ->where('is_active', true)
                ->orderBy('code')
                ->get();
        }

        return view('livewire.accounts.create', [
            'parents' => $parents,
            'accountTypes' => AccountType::cases(),
        ]);
    }
}
