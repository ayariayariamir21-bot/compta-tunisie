<?php

namespace App\Livewire\AccountingPeriods;

use App\Models\AccountingPeriod;
use App\Services\CurrentAccountingPeriod;
use App\Services\CurrentCompany;
use App\Services\CurrentFiscalYear;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Index extends Component
{
    public function close(AccountingPeriod $accountingPeriod, CurrentAccountingPeriod $currentAccountingPeriod): void
    {
        if (Auth::user()->cannot('close', $accountingPeriod)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        $currentFiscalYear = app(CurrentFiscalYear::class)->get(Auth::user());

        if (! $currentCompany || ! $currentFiscalYear) {
            abort(403);
        }

        $fiscalYear = $accountingPeriod->fiscalYear;

        if ($fiscalYear->company_id !== $currentCompany->id || $fiscalYear->id !== $currentFiscalYear->id) {
            abort(403);
        }

        $accountingPeriod->update(['is_open' => false, 'is_closed' => true]);

        $current = $currentAccountingPeriod->get(Auth::user());

        if ($current && $current->id === $accountingPeriod->id) {
            $currentAccountingPeriod->clear();
        }

        session()->flash('success', "La période « {$accountingPeriod->name} » a été clôturée.");

        $this->redirect(route('accounting-periods.index'), navigate: true);
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

        return view('livewire.accounting-periods.index', [
            'periods' => $periods,
            'currentCompany' => $company,
            'currentFiscalYear' => $fiscalYear,
        ]);
    }
}
