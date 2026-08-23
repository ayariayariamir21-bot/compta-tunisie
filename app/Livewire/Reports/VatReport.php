<?php

namespace App\Livewire\Reports;

use App\Services\Accounting\VatReportService;
use App\Services\CurrentCompany;
use App\Services\CurrentFiscalYear;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Rapport de TVA')]
class VatReport extends Component
{
    public string $fromDate = '';

    public string $toDate = '';

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

    public function render(VatReportService $vatReportService, CurrentCompany $currentCompany, CurrentFiscalYear $currentFiscalYear): View
    {
        $company = $currentCompany->get(Auth::user());
        $fiscalYear = $currentFiscalYear->get(Auth::user());

        $report = null;
        $summary = null;
        $dateError = null;

        if ($company && $fiscalYear) {
            try {
                $report = $vatReportService->getVatReport(
                    $company,
                    $fiscalYear,
                    $this->fromDate,
                    $this->toDate,
                );
                $summary = $vatReportService->getSummary($report);
            } catch (\InvalidArgumentException $exception) {
                $dateError = $exception->getMessage();
            }
        }

        return view('livewire.reports.vat-report', [
            'currentCompany' => $company,
            'currentFiscalYear' => $fiscalYear,
            'report' => $report,
            'summary' => $summary,
            'dateError' => $dateError,
        ]);
    }
}
