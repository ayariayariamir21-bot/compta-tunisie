<?php

namespace App\Livewire\Accounts;

use App\Enums\AccountType;
use App\Models\Account;
use App\Services\Accounting\AccountService;
use App\Services\Accounting\ChartOfAccountsSeeder;
use App\Services\CurrentCompany;
use App\Services\CurrentFiscalYear;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Index extends Component
{
    public string $search = '';

    public string $filterType = '';

    public function delete(Account $account, AccountService $accountService): void
    {
        if (Auth::user()->cannot('delete', $account)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        $currentFiscalYear = app(CurrentFiscalYear::class)->get(Auth::user());

        if (! $currentCompany || ! $currentFiscalYear) {
            abort(403);
        }

        if ($account->company_id !== $currentCompany->id || $account->fiscal_year_id !== $currentFiscalYear->id) {
            abort(403);
        }

        $accountService->deleteAccount($account);

        session()->flash('success', "Le compte « {$account->name} » a été supprimé.");

        $this->redirect(route('accounts.index'), navigate: true);
    }

    public function toggleActive(Account $account, AccountService $accountService): void
    {
        if (Auth::user()->cannot('activate', $account)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        $currentFiscalYear = app(CurrentFiscalYear::class)->get(Auth::user());

        if (! $currentCompany || ! $currentFiscalYear) {
            abort(403);
        }

        if ($account->company_id !== $currentCompany->id || $account->fiscal_year_id !== $currentFiscalYear->id) {
            abort(403);
        }

        $accountService->toggleActive($account);

        $label = $account->fresh()->is_active ? 'activé' : 'désactivé';
        session()->flash('success', "Le compte « {$account->name} » a été {$label}.");

        $this->redirect(route('accounts.index'), navigate: true);
    }

    public function initializeChart(CurrentCompany $currentCompany, CurrentFiscalYear $currentFiscalYear, ChartOfAccountsSeeder $seeder): void
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

        $count = $seeder->seed($company, $fiscalYear);

        if ($count > 0) {
            session()->flash('success', "{$count} compte(s) initialisé(s) avec succès.");
        } else {
            session()->flash('success', 'Le plan comptable est déjà initialisé.');
        }

        $this->redirect(route('accounts.index'), navigate: true);
    }

    public function render(CurrentCompany $currentCompany, CurrentFiscalYear $currentFiscalYear): View
    {
        $company = $currentCompany->get(Auth::user());
        $fiscalYear = $currentFiscalYear->get(Auth::user());

        $accounts = collect();

        if ($company && $fiscalYear) {
            $query = Account::where('company_id', $company->id)
                ->where('fiscal_year_id', $fiscalYear->id);

            if ($this->search !== '') {
                $search = $this->search;
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%");
                });
            }

            if ($this->filterType !== '') {
                $query->where('account_type', $this->filterType);
            }

            $accounts = $query->orderBy('code')->get();
        }

        return view('livewire.accounts.index', [
            'accounts' => $accounts,
            'currentCompany' => $company,
            'currentFiscalYear' => $fiscalYear,
            'accountTypes' => AccountType::cases(),
        ]);
    }
}
