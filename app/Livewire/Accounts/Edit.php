<?php

namespace App\Livewire\Accounts;

use App\Enums\AccountType;
use App\Models\Account;
use App\Services\Accounting\AccountService;
use App\Services\CurrentCompany;
use App\Services\CurrentFiscalYear;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Edit extends Component
{
    public ?Account $account = null;

    public string $code = '';

    public string $name = '';

    public ?AccountType $account_type = null;

    public ?int $parent_id = null;

    public string $description = '';

    public bool $is_active = true;

    public function mount(int $accountId, CurrentCompany $currentCompany, CurrentFiscalYear $currentFiscalYear): void
    {
        $account = Account::findOrFail($accountId);

        $company = $currentCompany->get(Auth::user());
        $fiscalYear = $currentFiscalYear->get(Auth::user());

        if (! $company || ! $fiscalYear) {
            abort(403);
        }

        if ($account->company_id !== $company->id || $account->fiscal_year_id !== $fiscalYear->id) {
            abort(403);
        }

        if (Auth::user()->cannot('update', $account)) {
            abort(403);
        }

        $this->account = $account;
        $this->code = $account->code;
        $this->name = $account->name;
        $this->account_type = $account->account_type;
        $this->parent_id = $account->parent_id;
        $this->description = $account->description ?? '';
        $this->is_active = $account->is_active;
    }

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

    public function update(CurrentCompany $currentCompany, CurrentFiscalYear $currentFiscalYear, AccountService $accountService): void
    {
        $company = $currentCompany->get(Auth::user());
        $fiscalYear = $currentFiscalYear->get(Auth::user());

        if (! $company || ! $fiscalYear) {
            session()->flash('error', 'Aucune société ou exercice sélectionné.');

            return;
        }

        if ($this->account->company_id !== $company->id || $this->account->fiscal_year_id !== $fiscalYear->id) {
            abort(403);
        }

        if (Auth::user()->cannot('update', $this->account)) {
            abort(403);
        }

        $validated = $this->validate();

        try {
            $accountService->updateAccount($this->account, $validated);
        } catch (\InvalidArgumentException $e) {
            $this->addError('code', $e->getMessage());

            return;
        }

        session()->flash('success', "Le compte « {$this->name} » a été mis à jour.");

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
                ->where('id', '!=', $this->account?->id)
                ->orderBy('code')
                ->get();
        }

        return view('livewire.accounts.edit', [
            'parents' => $parents,
            'accountTypes' => AccountType::cases(),
        ]);
    }
}
