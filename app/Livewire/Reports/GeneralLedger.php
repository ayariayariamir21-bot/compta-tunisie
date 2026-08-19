<?php

namespace App\Livewire\Reports;

use App\Services\Accounting\GeneralLedgerService;
use App\Services\CurrentCompany;
use App\Services\CurrentFiscalYear;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class GeneralLedger extends Component
{
    public ?int $accountId = null;

    public ?int $journalId = null;

    public string $fromDate = '';

    public string $toDate = '';

    public string $search = '';

    public function mount(): void
    {
        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        $currentFiscalYear = app(CurrentFiscalYear::class)->get(Auth::user());

        if ($currentCompany && $currentFiscalYear) {
            $this->fromDate = Carbon::parse($currentFiscalYear->start_date)->format('Y-m-d');
            $this->toDate = Carbon::parse($currentFiscalYear->end_date)->format('Y-m-d');
        }
    }

    public function render(GeneralLedgerService $generalLedgerService, CurrentCompany $currentCompany, CurrentFiscalYear $currentFiscalYear): View
    {
        $company = $currentCompany->get(Auth::user());
        $fiscalYear = $currentFiscalYear->get(Auth::user());

        $accounts = collect();
        $journals = collect();
        $ledgerData = [];

        if ($company && $fiscalYear) {
            $accounts = $generalLedgerService->getAccountsForContext($company, $fiscalYear);
            $journals = $generalLedgerService->getJournalsForContext($company, $fiscalYear);

            $filters = [
                'from_date' => $this->fromDate,
                'to_date' => $this->toDate,
                'journal_id' => $this->journalId,
                'search' => $this->search,
            ];

            $targetAccounts = $accounts;

            if ($this->accountId) {
                $targetAccounts = $accounts->where('id', $this->accountId);
            }

            foreach ($targetAccounts as $account) {
                $summary = $generalLedgerService->getLedgerSummary($account, $company, $fiscalYear, $filters);

                if ($summary['lines']->isNotEmpty() || $summary['opening_balance'] !== '0.000') {
                    $ledgerData[] = $summary;
                }
            }
        }

        return view('livewire.reports.general-ledger', [
            'currentCompany' => $company,
            'currentFiscalYear' => $fiscalYear,
            'accounts' => $accounts,
            'journals' => $journals,
            'ledgerData' => $ledgerData,
        ]);
    }
}
