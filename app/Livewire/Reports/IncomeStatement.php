<?php

namespace App\Livewire\Reports;

use App\Services\Accounting\IncomeStatementService;
use App\Services\CurrentCompany;
use App\Services\CurrentFiscalYear;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Compte de résultat')]
class IncomeStatement extends Component
{
    public string $fromDate = '';

    public string $toDate = '';

    public string $includeZeroBalance = '0';

    public function mount(): void
    {
        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        $currentFiscalYear = app(CurrentFiscalYear::class)->get(Auth::user());

        if ($currentCompany && $currentFiscalYear) {
            // Default period: from fiscal-year start to today clamped inside
            // the fiscal-year bounds, so the report always opens valid.
            $this->fromDate = Carbon::parse($currentFiscalYear->start_date)->format('Y-m-d');
            $this->toDate = Carbon::today()->min(Carbon::parse($currentFiscalYear->end_date))
                ->max(Carbon::parse($currentFiscalYear->start_date))
                ->format('Y-m-d');
        }
    }

    public function render(IncomeStatementService $incomeStatementService, CurrentCompany $currentCompany, CurrentFiscalYear $currentFiscalYear): View
    {
        $company = $currentCompany->get(Auth::user());
        $fiscalYear = $currentFiscalYear->get(Auth::user());

        $report = null;
        $dateError = null;

        if ($company && $fiscalYear) {
            try {
                $report = $incomeStatementService->getIncomeStatement(
                    $company,
                    $fiscalYear,
                    $this->fromDate,
                    $this->toDate,
                    $this->includeZeroBalance === '1',
                );
            } catch (\InvalidArgumentException $exception) {
                $dateError = $exception->getMessage();
            }
        }

        return view('livewire.reports.income-statement', [
            'currentCompany' => $company,
            'currentFiscalYear' => $fiscalYear,
            'report' => $report,
            'summary' => $report !== null ? $incomeStatementService->getSummary($report) : null,
            'dateError' => $dateError,
        ]);
    }
}
