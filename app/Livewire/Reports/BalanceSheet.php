<?php

namespace App\Livewire\Reports;

use App\Services\Accounting\BalanceSheetService;
use App\Services\CurrentCompany;
use App\Services\CurrentFiscalYear;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Bilan')]
class BalanceSheet extends Component
{
    public string $asOfDate = '';

    public string $includeZeroBalance = '0';

    public function mount(): void
    {
        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        $currentFiscalYear = app(CurrentFiscalYear::class)->get(Auth::user());

        if ($currentCompany && $currentFiscalYear) {
            // Default snapshot date: today clamped to the fiscal-year bounds,
            // so the report always opens with a valid in-range date.
            $this->asOfDate = Carbon::today()->min(Carbon::parse($currentFiscalYear->end_date))
                ->max(Carbon::parse($currentFiscalYear->start_date))
                ->format('Y-m-d');
        }
    }

    public function render(BalanceSheetService $balanceSheetService, CurrentCompany $currentCompany, CurrentFiscalYear $currentFiscalYear): View
    {
        $company = $currentCompany->get(Auth::user());
        $fiscalYear = $currentFiscalYear->get(Auth::user());

        $report = null;
        $dateError = null;

        if ($company && $fiscalYear) {
            try {
                $report = $balanceSheetService->getBalanceSheet(
                    $company,
                    $fiscalYear,
                    $this->asOfDate,
                    $this->includeZeroBalance === '1',
                );
            } catch (\InvalidArgumentException $exception) {
                $dateError = $exception->getMessage();
            }
        }

        return view('livewire.reports.balance-sheet', [
            'currentCompany' => $company,
            'currentFiscalYear' => $fiscalYear,
            'report' => $report,
            'summary' => $report !== null ? $balanceSheetService->getSummary($report) : null,
            'dateError' => $dateError,
        ]);
    }
}
