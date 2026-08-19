<?php

namespace App\Livewire\Reports;

use App\Enums\AccountType;
use App\Services\Accounting\TrialBalanceService;
use App\Services\CurrentCompany;
use App\Services\CurrentFiscalYear;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class TrialBalance extends Component
{
    public string $fromDate = '';

    public string $toDate = '';

    public string $accountType = '';

    public ?int $accountId = null;

    public string $includeZeroBalance = '0';

    public function mount(): void
    {
        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        $currentFiscalYear = app(CurrentFiscalYear::class)->get(Auth::user());

        if ($currentCompany && $currentFiscalYear) {
            $this->fromDate = Carbon::parse($currentFiscalYear->start_date)->format('Y-m-d');
            $this->toDate = Carbon::parse($currentFiscalYear->end_date)->format('Y-m-d');
        }
    }

    public function render(TrialBalanceService $trialBalanceService, CurrentCompany $currentCompany, CurrentFiscalYear $currentFiscalYear): View
    {
        $company = $currentCompany->get(Auth::user());
        $fiscalYear = $currentFiscalYear->get(Auth::user());

        $trialBalance = [
            'accounts' => collect(),
            'total_debit' => '0.000',
            'total_credit' => '0.000',
            'total_debit_balance' => '0.000',
            'total_credit_balance' => '0.000',
            'is_balanced' => true,
            'difference' => '0.000',
        ];

        if ($company && $fiscalYear) {
            $filters = [
                'from_date' => $this->fromDate,
                'to_date' => $this->toDate,
                'account_type' => $this->accountType,
                'account_id' => $this->accountId,
                'include_zero_balance' => $this->includeZeroBalance === '1',
            ];

            $trialBalance = $trialBalanceService->getTrialBalance($company, $fiscalYear, $filters);
        }

        return view('livewire.reports.trial-balance', [
            'currentCompany' => $company,
            'currentFiscalYear' => $fiscalYear,
            'trialBalance' => $trialBalance,
            'accountTypes' => AccountType::cases(),
        ]);
    }
}
