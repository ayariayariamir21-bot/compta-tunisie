<?php

namespace App\Services\Reporting;

use App\Models\Account;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FiscalYear;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\BalanceSheetService;
use App\Services\Accounting\CustomerStatementService;
use App\Services\Accounting\GeneralLedgerService;
use App\Services\Accounting\IncomeStatementService;
use App\Services\Accounting\SupplierStatementService;
use App\Services\Accounting\TrialBalanceService;
use App\Services\Accounting\VatReportService;
use App\Services\CurrentCompany;
use App\Services\CurrentFiscalYear;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Stateless PDF exporter for the existing accounting reports.
 *
 * This layer never duplicates accounting calculations: every report is built
 * by its dedicated service (single source of truth) and only rendered to a
 * print-friendly HTML document before conversion through Dompdf.
 *
 * @phpstan-type LedgerSummary array{account: Account, opening_balance: numeric-string, lines: Collection<int, \App\Models\JournalEntryLine>, closing_balance: numeric-string}
 */
final class PdfReportService
{
    public function __construct(
        private CurrentCompany $currentCompany,
        private CurrentFiscalYear $currentFiscalYear,
        private GeneralLedgerService $generalLedgerService,
        private TrialBalanceService $trialBalanceService,
        private BalanceSheetService $balanceSheetService,
        private IncomeStatementService $incomeStatementService,
        private VatReportService $vatReportService,
        private CustomerStatementService $customerStatementService,
        private SupplierStatementService $supplierStatementService,
    ) {}

    // ---------- General Ledger ----------

    /**
     * Build the Grand Livre PDF view for the current company context.
     *
     * @throws ModelNotFoundException<Account>|InvalidArgumentException
     */
    public function generalLedgerView(User $user, ?int $accountId, ?int $journalId, string $fromDate, string $toDate, string $search): View
    {
        [$company, $fiscalYear] = $this->resolveContext($user);

        $filters = [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'journal_id' => $journalId,
            'search' => $search,
        ];

        /** @var list<LedgerSummary> $ledgerData */
        $ledgerData = [];

        if ($accountId !== null) {
            $accountFilter = Account::where('company_id', $company->id)
                ->where('fiscal_year_id', $fiscalYear->id)
                ->where('id', $accountId)
                ->firstOrFail();

            $summary = $this->generalLedgerService->getLedgerSummary($accountFilter, $company, $fiscalYear, $filters);

            if ($summary['lines']->isNotEmpty() || $summary['opening_balance'] !== '0.000') {
                $ledgerData[] = $summary;
            }
        } else {
            $accounts = $this->generalLedgerService->getAccountsForContext($company, $fiscalYear);

            // Batched: two queries total instead of two per account.
            foreach ($this->generalLedgerService->getLedgerSummaries($accounts, $company, $fiscalYear, $filters) as $summary) {
                if ($summary['lines']->isNotEmpty() || $summary['opening_balance'] !== '0.000') {
                    $ledgerData[] = $summary;
                }
            }
        }

        return view('pdf.reports.general-ledger', [
            'company' => $company,
            'fiscalYear' => $fiscalYear,
            'title' => 'Grand Livre',
            'periodLabel' => $this->rangeLabel($fromDate, $toDate),
            'accountFilter' => isset($accountFilter) ? $accountFilter : null,
            'ledgerData' => $ledgerData,
        ]);
    }

    /**
     * Export the full Grand Livre as landscape A4 PDF.
     *
     * @return array{content: string, filename: string}
     */
    public function exportGeneralLedger(User $user, ?int $accountId, ?int $journalId, string $fromDate, string $toDate, string $search): array
    {
        return $this->render(
            $this->generalLedgerView($user, $accountId, $journalId, $fromDate, $toDate, $search),
            'landscape',
            sprintf('grand-livre-%s-%s.pdf', $this->dateSlug($fromDate), $this->dateSlug($toDate)),
        );
    }

    // ---------- Trial Balance ----------

    /**
     * Build the Balance (trial balance) PDF view.
     *
     * @throws InvalidArgumentException
     */
    public function trialBalanceView(User $user, string $fromDate, string $toDate, string $accountType, ?int $accountId, string $includeZeroBalance): View
    {
        [$company, $fiscalYear] = $this->resolveContext($user);

        $filters = [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'account_type' => $accountType,
            'account_id' => $accountId,
            'include_zero_balance' => $includeZeroBalance,
        ];

        return view('pdf.reports.trial-balance', [
            'company' => $company,
            'fiscalYear' => $fiscalYear,
            'title' => 'Balance des comptes',
            'periodLabel' => $this->rangeLabel($fromDate, $toDate),
            'trialBalance' => $this->trialBalanceService->getTrialBalance($company, $fiscalYear, $filters),
        ]);
    }

    /**
     * Export the trial balance as landscape A4 PDF.
     *
     * @return array{content: string, filename: string}
     */
    public function exportTrialBalance(User $user, string $fromDate, string $toDate, string $accountType, ?int $accountId, string $includeZeroBalance): array
    {
        return $this->render(
            $this->trialBalanceView($user, $fromDate, $toDate, $accountType, $accountId, $includeZeroBalance),
            'landscape',
            sprintf('balance-%s-%s.pdf', $this->dateSlug($fromDate), $this->dateSlug($toDate)),
        );
    }

    // ---------- Balance Sheet ----------

    /**
     * Build the Bilan PDF view.
     *
     * @throws InvalidArgumentException
     */
    public function balanceSheetView(User $user, string $asOfDate, string $includeZeroBalance): View
    {
        [$company, $fiscalYear] = $this->resolveContext($user);

        $report = $this->balanceSheetService->getBalanceSheet(
            $company,
            $fiscalYear,
            $asOfDate,
            $includeZeroBalance === '1',
        );

        return view('pdf.reports.balance-sheet', [
            'company' => $company,
            'fiscalYear' => $fiscalYear,
            'title' => 'Bilan',
            'periodLabel' => 'Au '.$this->displayDate($report['as_of_date']),
            'report' => $report,
            'summary' => $this->balanceSheetService->getSummary($report),
        ]);
    }

    /**
     * Export the balance sheet as portrait A4 PDF.
     *
     * @return array{content: string, filename: string}
     */
    public function exportBalanceSheet(User $user, string $asOfDate, string $includeZeroBalance): array
    {
        return $this->render(
            $this->balanceSheetView($user, $asOfDate, $includeZeroBalance),
            'portrait',
            sprintf('bilan-%s.pdf', $this->dateSlug($asOfDate)),
        );
    }

    // ---------- Income Statement ----------

    /**
     * Build the Compte de résultat PDF view.
     *
     * @throws InvalidArgumentException
     */
    public function incomeStatementView(User $user, string $fromDate, string $toDate, string $includeZeroBalance): View
    {
        [$company, $fiscalYear] = $this->resolveContext($user);

        $report = $this->incomeStatementService->getIncomeStatement(
            $company,
            $fiscalYear,
            $fromDate,
            $toDate,
            $includeZeroBalance === '1',
        );

        return view('pdf.reports.income-statement', [
            'company' => $company,
            'fiscalYear' => $fiscalYear,
            'title' => 'Compte de résultat',
            'periodLabel' => $this->rangeLabel($report['from_date'], $report['to_date']),
            'report' => $report,
            'summary' => $this->incomeStatementService->getSummary($report),
        ]);
    }

    /**
     * Export the income statement as portrait A4 PDF.
     *
     * @return array{content: string, filename: string}
     */
    public function exportIncomeStatement(User $user, string $fromDate, string $toDate, string $includeZeroBalance): array
    {
        return $this->render(
            $this->incomeStatementView($user, $fromDate, $toDate, $includeZeroBalance),
            'portrait',
            sprintf('compte-resultat-%s-%s.pdf', $this->dateSlug($fromDate), $this->dateSlug($toDate)),
        );
    }

    // ---------- VAT Report ----------

    /**
     * Build the Rapport de TVA PDF view.
     *
     * @throws InvalidArgumentException
     */
    public function vatReportView(User $user, string $fromDate, string $toDate): View
    {
        [$company, $fiscalYear] = $this->resolveContext($user);

        $report = $this->vatReportService->getVatReport($company, $fiscalYear, $fromDate, $toDate);

        return view('pdf.reports.vat-report', [
            'company' => $company,
            'fiscalYear' => $fiscalYear,
            'title' => 'Rapport de TVA',
            'periodLabel' => $this->rangeLabel($report['from_date'], $report['to_date']),
            'report' => $report,
            'summary' => $this->vatReportService->getSummary($report),
        ]);
    }

    /**
     * Export the VAT report as portrait A4 PDF.
     *
     * @return array{content: string, filename: string}
     */
    public function exportVatReport(User $user, string $fromDate, string $toDate): array
    {
        return $this->render(
            $this->vatReportView($user, $fromDate, $toDate),
            'portrait',
            sprintf('tva-%s-%s.pdf', $this->dateSlug($fromDate), $this->dateSlug($toDate)),
        );
    }

    // ---------- Customer Statement ----------

    /**
     * Build the Relevé client PDF view; the customer must belong to the
     * current company or the lookup fails with a 404-grade exception.
     *
     * @throws ModelNotFoundException<Customer>|InvalidArgumentException
     */
    public function customerStatementView(User $user, int $customerId, ?string $fromDate, ?string $toDate): View
    {
        [$company] = $this->resolveContext($user);

        $customer = Customer::where('id', $customerId)
            ->where('company_id', $company->id)
            ->firstOrFail();

        $statement = $this->customerStatementService->getStatement($customer, $fromDate, $toDate);

        return view('pdf.reports.customer-statement', [
            'company' => $company,
            'title' => 'Relevé client',
            'periodLabel' => $this->optionalRangeLabel($statement['from_date'], $statement['to_date']),
            'customer' => $customer,
            'statement' => $statement,
        ]);
    }

    /**
     * Export the customer statement as portrait A4 PDF.
     *
     * @return array{content: string, filename: string}
     */
    public function exportCustomerStatement(User $user, int $customerId, ?string $fromDate, ?string $toDate): array
    {
        [$company, $fiscalYear] = $this->resolveContext($user);

        $fromSlug = $fromDate !== null && $fromDate !== '' ? $fromDate : $this->dateSlug((string) $fiscalYear->start_date);
        $toSlug = $toDate !== null && $toDate !== '' ? $toDate : $this->dateSlug((string) $fiscalYear->end_date);

        $customer = Customer::where('id', $customerId)->where('company_id', $company->id)->first();

        $segment = $customer instanceof Customer ? $this->sanitizeSegment($customer->code) : 'client';

        return $this->render(
            $this->customerStatementView($user, $customerId, $fromDate, $toDate),
            'portrait',
            sprintf('releve-client-%s-%s-%s.pdf', $segment, $fromSlug, $toSlug),
        );
    }

    // ---------- Supplier Statement ----------

    /**
     * Build the Relevé fournisseur PDF view; the supplier must belong to the
     * current company or the lookup fails with a 404-grade exception.
     *
     * @throws ModelNotFoundException<Supplier>|InvalidArgumentException
     */
    public function supplierStatementView(User $user, int $supplierId, ?string $fromDate, ?string $toDate): View
    {
        [$company] = $this->resolveContext($user);

        $supplier = Supplier::where('id', $supplierId)
            ->where('company_id', $company->id)
            ->firstOrFail();

        $statement = $this->supplierStatementService->getStatement($supplier, $fromDate, $toDate);

        return view('pdf.reports.supplier-statement', [
            'company' => $company,
            'title' => 'Relevé fournisseur',
            'periodLabel' => $this->optionalRangeLabel($statement['from_date'], $statement['to_date']),
            'supplier' => $supplier,
            'statement' => $statement,
        ]);
    }

    /**
     * Export the supplier statement as portrait A4 PDF.
     *
     * @return array{content: string, filename: string}
     */
    public function exportSupplierStatement(User $user, int $supplierId, ?string $fromDate, ?string $toDate): array
    {
        [$company, $fiscalYear] = $this->resolveContext($user);

        $fromSlug = $fromDate !== null && $fromDate !== '' ? $fromDate : $this->dateSlug((string) $fiscalYear->start_date);
        $toSlug = $toDate !== null && $toDate !== '' ? $toDate : $this->dateSlug((string) $fiscalYear->end_date);

        $supplier = Supplier::where('id', $supplierId)->where('company_id', $company->id)->first();

        $segment = $supplier instanceof Supplier ? $this->sanitizeSegment($supplier->code) : 'fournisseur';

        return $this->render(
            $this->supplierStatementView($user, $supplierId, $fromDate, $toDate),
            'portrait',
            sprintf('releve-fournisseur-%s-%s-%s.pdf', $segment, $fromSlug, $toSlug),
        );
    }

    // ---------- Internals ----------

    /**
     * Resolve the reporting context from session state only; browser input
     * can never select another company.
     *
     * @return array{0: Company, 1: FiscalYear}
     *
     * @throws InvalidArgumentException
     */
    private function resolveContext(User $user): array
    {
        $company = $this->currentCompany->get($user);
        $fiscalYear = $this->currentFiscalYear->get($user);

        if (! $company instanceof Company || ! $fiscalYear instanceof FiscalYear) {
            throw new InvalidArgumentException('Aucun contexte comptable sélectionné.');
        }

        if ($fiscalYear->company_id !== $company->id) {
            throw new InvalidArgumentException("L'exercice n'appartient pas à cette société.");
        }

        return [$company, $fiscalYear];
    }

    /**
     * Render an HTML view to a PDF binary with the given orientation.
     *
     * @return array{content: string, filename: string}
     */
    private function render(View $view, string $orientation, string $filename): array
    {
        $dompdf = new Dompdf([
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'defaultFont' => 'DejaVu Sans',
        ]);

        $dompdf->setPaper('a4', $orientation);
        $dompdf->loadHtml($view->render());
        $dompdf->render();

        return [
            'content' => (string) $dompdf->output(),
            'filename' => $filename,
        ];
    }

    /**
     * Normalize any date-ish input to Y-m-d.
     */
    private function dateSlug(string $value): string
    {
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception) {
            return 'sans-date';
        }
    }

    /**
     * Display date in the French dd/mm/YYYY convention.
     */
    private function displayDate(string $value): string
    {
        return Carbon::parse($value)->format('d/m/Y');
    }

    /**
     * "Du 01/01/2026 au 31/12/2026" style period label.
     */
    private function rangeLabel(string $fromDate, string $toDate): string
    {
        return sprintf('Du %s au %s', $this->displayDate($fromDate), $this->displayDate($toDate));
    }

    /**
     * Period label tolerant of open-ended statement ranges.
     */
    private function optionalRangeLabel(?string $fromDate, ?string $toDate): string
    {
        if ($fromDate !== null && $toDate !== null) {
            return $this->rangeLabel($fromDate, $toDate);
        }

        if ($toDate !== null) {
            return "Jusqu'au ".$this->displayDate($toDate);
        }

        if ($fromDate !== null) {
            return 'À partir du '.$this->displayDate($fromDate);
        }

        return 'Période complète';
    }

    /**
     * Sanitize a human identifier for safe inclusion in filenames.
     */
    private function sanitizeSegment(string $value): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9_-]+/', '', trim($value)) ?? '';

        return $sanitized === '' ? 'document' : $sanitized;
    }
}
