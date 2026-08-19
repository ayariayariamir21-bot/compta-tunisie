<?php

namespace App\Livewire;

use App\Models\AccountingPeriod;
use App\Services\CurrentAccountingPeriod;
use App\Services\CurrentCompany;
use App\Services\CurrentFiscalYear;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AccountingPeriodSwitcher extends Component
{
    public ?int $periodId = null;

    public function mount(CurrentAccountingPeriod $currentAccountingPeriod): void
    {
        $period = $currentAccountingPeriod->get(Auth::user());

        $this->periodId = $period?->id;
    }

    public function switchPeriod(CurrentAccountingPeriod $currentAccountingPeriod): void
    {
        $this->validate([
            'periodId' => ['nullable', 'integer'],
        ]);

        if (! $this->periodId) {
            $currentAccountingPeriod->clear();

            $this->redirect(url()->previous(), navigate: true);

            return;
        }

        $success = $currentAccountingPeriod->set(
            Auth::user(),
            $this->periodId
        );

        if (! $success) {
            $this->addError(
                'periodId',
                'You do not have access to this period.'
            );

            return;
        }

        $this->redirect(url()->previous(), navigate: true);
    }

    public function render(CurrentCompany $currentCompany, CurrentFiscalYear $currentFiscalYear)
    {
        $company = $currentCompany->get(Auth::user());
        $fiscalYear = $currentFiscalYear->get(Auth::user());

        $periods = collect();

        if ($company && $fiscalYear) {
            $periods = AccountingPeriod::where('fiscal_year_id', $fiscalYear->id)
                ->orderBy('start_date')
                ->get();
        }

        return view('livewire.accounting-period-switcher', [
            'periods' => $periods,
        ]);
    }
}
