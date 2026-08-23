<?php

namespace App\Services\Accounting;

use App\Enums\TaxType;
use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\TaxRate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Read-only VAT (TVA) reporting service.
 *
 * The authoritative VAT figures come from POSTED journal entry lines on the
 * accounts configured as VAT accounts in the tax-rate configuration:
 *
 * - collected VAT (TVA collectée): credits - debits on the sales VAT
 *   accounts referenced by TaxRate::sales_tax_account_id (sales invoices
 *   credit them, sales credit notes debit them back);
 * - deductible VAT (TVA déductible): debits - credits on the purchase VAT
 *   accounts referenced by TaxRate::purchase_tax_account_id (purchase
 *   invoices and expenses debit them).
 *
 * No account code is ever invented: an account only becomes a "VAT account"
 * when at least one tax rate of the company references it. Payments never
 * create VAT and are therefore never queried. Draft and cancelled documents
 * are excluded because their journal entries are not POSTED.
 *
 * Document-level details and per-rate breakdowns are rebuilt from the
 * historical tax snapshots stored on posted document lines (tax_code,
 * tax_rate, tax_amount, line_subtotal), joined through the document headers
 * by company + fiscal year + status + date window. Because every posting
 * service writes one journal entry per document with entry_date = document
 * date, ledger totals and document details always cover the same window.
 *
 * Movements booked on a VAT account without any matching document line
 * (manual OD entries) are never hidden: they surface as «Autres mouvements
 * TVA» so the report can be reconciled against the general ledger.
 *
 * @phpstan-type VatLedgerRow array{account_id: int, code: string, name: string, debit: numeric-string, credit: numeric-string}
 * @phpstan-type VatDetailRow array{date: string, direction: string, direction_label: string, document_type: string, document_type_label: string, document_number: string, party: string, tax_rate_id: int|null, code: string, rate: string, base: numeric-string, vat: numeric-string, type: string|null}
 * @phpstan-type VatRateRow array{direction: string, direction_label: string, tax_rate_id: int|null, code: string, rate: string, type: string, type_label: string, base: numeric-string, vat: numeric-string}
 */
class VatReportService
{
    private const DIRECTION_OUTPUT = 'collectee';

    private const DIRECTION_INPUT = 'deductible';

    /**
     * Validate that the context is coherent for a VAT period.
     *
     * Historical dates inside already-closed periods are allowed on purpose:
     * this is a reporting screen, not a transaction screen. Both dates must
     * be well-formed, belong to the selected fiscal year and be ordered.
     *
     * @throws InvalidArgumentException
     */
    public function validateContext(Company $company, FiscalYear $fiscalYear, string $fromDate, string $toDate): void
    {
        if ($fiscalYear->company_id !== $company->id) {
            throw new InvalidArgumentException('L\'exercice n\'appartient pas à cette société.');
        }

        try {
            $from = Carbon::createFromFormat('Y-m-d', $fromDate)?->startOfDay();
        } catch (InvalidArgumentException) {
            $from = false;
        }

        if ($from === false || $from->format('Y-m-d') !== $fromDate) {
            throw new InvalidArgumentException('La date de début est invalide.');
        }

        try {
            $to = Carbon::createFromFormat('Y-m-d', $toDate)?->startOfDay();
        } catch (InvalidArgumentException) {
            $to = false;
        }

        if ($to === false || $to->format('Y-m-d') !== $toDate) {
            throw new InvalidArgumentException('La date de fin est invalide.');
        }

        $start = Carbon::parse($fiscalYear->start_date)->startOfDay();
        $end = Carbon::parse($fiscalYear->end_date)->startOfDay();

        if ($from->lt($start) || $from->gt($end) || $to->lt($start) || $to->gt($end)) {
            $formattedStart = $start->format('d/m/Y');
            $formattedEnd = $end->format('d/m/Y');

            throw new InvalidArgumentException("Les dates du rapport de TVA doivent être comprises entre le {$formattedStart} et le {$formattedEnd}.");
        }

        if ($from->gt($to)) {
            throw new InvalidArgumentException('La date de début doit être antérieure ou égale à la date de fin.');
        }
    }

    /**
     * Build the full VAT report for the current company/fiscal year period.
     *
     * Defaults chosen by the Livewire page: from_date = fiscal-year start,
     * to_date = today clamped inside the fiscal-year bounds.
     *
     * @return array{from_date: string, to_date: string, collected_vat: numeric-string, deductible_vat: numeric-string, net_vat: numeric-string, has_payable: bool, has_credit: bool, is_neutral: bool, total_output_base: numeric-string, total_input_base: numeric-string, output_accounts: list<VatLedgerRow>, input_accounts: list<VatLedgerRow>, tax_rates: list<VatRateRow>, documents: list<VatDetailRow>, unattributed_collected: numeric-string, unattributed_deductible: numeric-string}
     */
    public function getVatReport(Company $company, FiscalYear $fiscalYear, string $fromDate, string $toDate): array
    {
        $this->validateContext($company, $fiscalYear, $fromDate, $toDate);

        [$outputAccounts, $inputAccounts] = $this->resolveVatAccounts($company, $fiscalYear);

        [$ledgerByAccountId] = $this->fetchLedgerMovements(
            $company,
            $fiscalYear,
            $fromDate,
            $toDate,
            $outputAccounts->merge($inputAccounts)->keyBy('id'),
        );

        [$collectedVat, $collectedRows] = $this->sumLedgerDirection($ledgerByAccountId, $outputAccounts, true);
        [$deductibleVat, $deductibleRows] = $this->sumLedgerDirection($ledgerByAccountId, $inputAccounts, false);

        $documents = $this->fetchDocumentDetails($company, $fiscalYear, $fromDate, $toDate);
        $taxRates = $this->getTaxRateBreakdown($documents);

        [$attributedCollected, $totalOutputBase] = $this->sumDocumentDirection($documents, self::DIRECTION_OUTPUT);
        [$attributedDeductible, $totalInputBase] = $this->sumDocumentDirection($documents, self::DIRECTION_INPUT);

        $netVat = $this->getNetVat($collectedVat, $deductibleVat);

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'collected_vat' => $collectedVat,
            'deductible_vat' => $deductibleVat,
            'net_vat' => $netVat,
            'has_payable' => bccomp($netVat, '0.000', 3) > 0,
            'has_credit' => bccomp($netVat, '0.000', 3) < 0,
            'is_neutral' => bccomp($netVat, '0.000', 3) === 0,
            'total_output_base' => $totalOutputBase,
            'total_input_base' => $totalInputBase,
            'output_accounts' => $collectedRows,
            'input_accounts' => $deductibleRows,
            'tax_rates' => $taxRates,
            'documents' => $documents,
            // Manual or legacy movements sitting on VAT accounts without any
            // matching posted document line. Never discarded, always shown.
            'unattributed_collected' => bcsub($collectedVat, $attributedCollected, 3),
            'unattributed_deductible' => bcsub($deductibleVat, $attributedDeductible, 3),
        ];
    }

    /**
     * Collected VAT over the period: credits - debits on sales VAT accounts.
     *
     * @return numeric-string
     */
    public function getCollectedVat(Company $company, FiscalYear $fiscalYear, string $fromDate, string $toDate): string
    {
        $this->validateContext($company, $fiscalYear, $fromDate, $toDate);

        [$outputAccounts] = $this->resolveVatAccounts($company, $fiscalYear);

        [$ledgerByAccountId] = $this->fetchLedgerMovements($company, $fiscalYear, $fromDate, $toDate, $outputAccounts->keyBy('id'));

        [$collectedVat] = $this->sumLedgerDirection($ledgerByAccountId, $outputAccounts, true);

        return $collectedVat;
    }

    /**
     * Deductible VAT over the period: debits - credits on purchase VAT accounts.
     *
     * @return numeric-string
     */
    public function getDeductibleVat(Company $company, FiscalYear $fiscalYear, string $fromDate, string $toDate): string
    {
        $this->validateContext($company, $fiscalYear, $fromDate, $toDate);

        [, $inputAccounts] = $this->resolveVatAccounts($company, $fiscalYear);

        [$ledgerByAccountId] = $this->fetchLedgerMovements($company, $fiscalYear, $fromDate, $toDate, $inputAccounts->keyBy('id'));

        [$deductibleVat] = $this->sumLedgerDirection($ledgerByAccountId, $inputAccounts, false);

        return $deductibleVat;
    }

    /**
     * Net VAT position: collected - deductible.
     *
     * @param  numeric-string  $collectedVat
     * @param  numeric-string  $deductibleVat
     * @return numeric-string
     */
    public function getNetVat(string $collectedVat, string $deductibleVat): string
    {
        return bcsub($collectedVat, $deductibleVat, 3);
    }

    /**
     * Human label for the net VAT position.
     *
     * @param  numeric-string  $netVat
     */
    public function getStatusLabel(string $netVat): string
    {
        $comparison = bccomp($netVat, '0.000', 3);

        if ($comparison > 0) {
            return 'TVA à payer';
        }

        if ($comparison < 0) {
            return 'Crédit de TVA';
        }

        return 'Aucun solde de TVA';
    }

    /**
     * Per-tax-rate breakdown built from posted document line snapshots.
     *
     * Exempt and zero-rated lines carry tax_amount = 0 but still belong in
     * their base column; they are grouped exactly like taxable rates. Sales
     * and purchase sides are never merged: the same rate may legitimately
     * appear once per direction.
     *
     * @param  list<VatDetailRow>  $details
     * @return list<VatRateRow>
     */
    public function getTaxRateBreakdown(array $details): array
    {
        /** @var array<string, VatRateRow> $grouped */
        $grouped = [];

        foreach ($details as $detail) {
            $rateId = $detail['tax_rate_id'];
            $key = $detail['direction'].'|'.($rateId !== null ? 'id:'.$rateId : 'code:'.$detail['code'].'@'.$detail['rate']);

            if (! isset($grouped[$key])) {
                $type = TaxType::tryFrom($detail['type'] ?? '');

                $grouped[$key] = [
                    'direction' => $detail['direction'],
                    'direction_label' => $detail['direction_label'],
                    'tax_rate_id' => $rateId,
                    'code' => $detail['code'],
                    'rate' => $detail['rate'],
                    'type' => $detail['type'] ?? TaxType::OTHER->value,
                    'type_label' => $type?->label() ?? TaxType::OTHER->label(),
                    'base' => '0.000',
                    'vat' => '0.000',
                ];
            }

            $grouped[$key]['base'] = bcadd($grouped[$key]['base'], $detail['base'], 3);
            $grouped[$key]['vat'] = bcadd($grouped[$key]['vat'], $detail['vat'], 3);
        }

        $rows = array_values($grouped);

        usort($rows, function (array $a, array $b): int {
            $byDirection = strcmp($a['direction'], $b['direction']);

            if ($byDirection !== 0) {
                return $byDirection;
            }

            return strcmp($a['code'].$a['rate'], $b['code'].$b['rate']);
        });

        return $rows;
    }

    /**
     * Chronological output-side (collected) document details.
     *
     * @return list<VatDetailRow>
     */
    public function getOutputVatDetails(Company $company, FiscalYear $fiscalYear, string $fromDate, string $toDate): array
    {
        $this->validateContext($company, $fiscalYear, $fromDate, $toDate);

        return $this->filterDetailsByDirection(
            $this->fetchDocumentDetails($company, $fiscalYear, $fromDate, $toDate),
            self::DIRECTION_OUTPUT,
        );
    }

    /**
     * Chronological input-side (deductible) document details.
     *
     * @return list<VatDetailRow>
     */
    public function getInputVatDetails(Company $company, FiscalYear $fiscalYear, string $fromDate, string $toDate): array
    {
        $this->validateContext($company, $fiscalYear, $fromDate, $toDate);

        return $this->filterDetailsByDirection(
            $this->fetchDocumentDetails($company, $fiscalYear, $fromDate, $toDate),
            self::DIRECTION_INPUT,
        );
    }

    /**
     * Flat summary for headline cards.
     *
     * @param  array{from_date: string, to_date: string, collected_vat: numeric-string, deductible_vat: numeric-string, net_vat: numeric-string, has_payable: bool, has_credit: bool, is_neutral: bool, total_output_base: numeric-string, total_input_base: numeric-string}  $report
     * @return array{from_date: string, to_date: string, collected_vat: numeric-string, deductible_vat: numeric-string, net_vat: numeric-string, status_label: string, has_payable: bool, has_credit: bool, is_neutral: bool, total_output_base: numeric-string, total_input_base: numeric-string}
     */
    public function getSummary(array $report): array
    {
        return [
            'from_date' => $report['from_date'],
            'to_date' => $report['to_date'],
            'collected_vat' => $report['collected_vat'],
            'deductible_vat' => $report['deductible_vat'],
            'net_vat' => $report['net_vat'],
            'status_label' => $this->getStatusLabel($report['net_vat']),
            'has_payable' => $report['has_payable'],
            'has_credit' => $report['has_credit'],
            'is_neutral' => $report['is_neutral'],
            'total_output_base' => $report['total_output_base'],
            'total_input_base' => $report['total_input_base'],
        ];
    }

    /**
     * Reconcile document-attributed VAT with ledger totals for the window.
     *
     * Differences arise from manual journal entries touching VAT accounts;
     * a balanced result means every centime of ledger VAT is explained by a
     * posted document.
     *
     * @return array{ledger_collected: numeric-string, document_collected: numeric-string, difference_collected: numeric-string, ledger_deductible: numeric-string, document_deductible: numeric-string, difference_deductible: numeric-string, is_balanced: bool}
     */
    public function reconcileWithLedger(Company $company, FiscalYear $fiscalYear, string $fromDate, string $toDate): array
    {
        $this->validateContext($company, $fiscalYear, $fromDate, $toDate);

        [$outputAccounts, $inputAccounts] = $this->resolveVatAccounts($company, $fiscalYear);

        [$ledgerByAccountId] = $this->fetchLedgerMovements(
            $company,
            $fiscalYear,
            $fromDate,
            $toDate,
            $outputAccounts->merge($inputAccounts)->keyBy('id'),
        );

        [$ledgerCollected] = $this->sumLedgerDirection($ledgerByAccountId, $outputAccounts, true);
        [$ledgerDeductible] = $this->sumLedgerDirection($ledgerByAccountId, $inputAccounts, false);

        $documents = $this->fetchDocumentDetails($company, $fiscalYear, $fromDate, $toDate);
        [$documentCollected] = $this->sumDocumentDirection($documents, self::DIRECTION_OUTPUT);
        [$documentDeductible] = $this->sumDocumentDirection($documents, self::DIRECTION_INPUT);

        $differenceCollected = bcsub($ledgerCollected, $documentCollected, 3);
        $differenceDeductible = bcsub($ledgerDeductible, $documentDeductible, 3);

        return [
            'ledger_collected' => $ledgerCollected,
            'document_collected' => $documentCollected,
            'difference_collected' => $differenceCollected,
            'ledger_deductible' => $ledgerDeductible,
            'document_deductible' => $documentDeductible,
            'difference_deductible' => $differenceDeductible,
            'is_balanced' => bccomp($differenceCollected, '0.000', 3) === 0
                && bccomp($differenceDeductible, '0.000', 3) === 0,
        ];
    }

    /**
     * Resolve the configured VAT accounts for both directions.
     *
     * An account participates when at least one tax rate of the company
     * references it — including deactivated rates, whose historical postings
     * must keep appearing in reports. Account ids that do not exist in the
     * current fiscal year chart are silently skipped.
     *
     * @return array{0: Collection<int|string, Account>, 1: Collection<int|string, Account>}
     */
    private function resolveVatAccounts(Company $company, FiscalYear $fiscalYear): array
    {
        $rates = TaxRate::query()
            ->where('company_id', $company->id)
            ->get(['sales_tax_account_id', 'purchase_tax_account_id']);

        $outputIds = $rates->pluck('sales_tax_account_id')->filter()->map(fn ($id) => (int) $id)->unique()->all();
        $inputIds = $rates->pluck('purchase_tax_account_id')->filter()->map(fn ($id) => (int) $id)->unique()->all();

        $accountsById = Account::query()
            ->where('company_id', $company->id)
            ->where('fiscal_year_id', $fiscalYear->id)
            ->whereIn('id', array_merge($outputIds, $inputIds))
            ->orderBy('code')
            ->get()
            ->keyBy('id');

        return [
            $accountsById->only($outputIds),
            $accountsById->only($inputIds),
        ];
    }

    /**
     * Aggregate posted ledger movements per account over the window.
     *
     * The lower bound uses a bare date and the upper bound an explicit
     * end-of-day timestamp so both boundaries stay inclusive even where
     * entry dates carry a time component (SQLite compares entry_date values
     * as strings). Returns [] when no VAT accounts are configured.
     *
     * @param  Collection<int|string, Account>  $accountsById
     * @return array{array<int, array{debit: numeric-string, credit: numeric-string}>, Collection<int|string, Account>}
     */
    private function fetchLedgerMovements(Company $company, FiscalYear $fiscalYear, string $fromDate, string $toDate, Collection $accountsById): array
    {
        if ($accountsById->isEmpty()) {
            return [[], $accountsById];
        }

        $fromBound = Carbon::parse($fromDate)->format('Y-m-d');
        $toBound = Carbon::parse($toDate)->endOfDay()->format('Y-m-d H:i:s');

        $rows = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.company_id', $company->id)
            ->where('journal_entries.fiscal_year_id', $fiscalYear->id)
            ->where('journal_entries.status', 'posted')
            ->where('journal_entries.entry_date', '>=', $fromBound)
            ->where('journal_entries.entry_date', '<=', $toBound)
            ->whereIn('journal_entry_lines.account_id', $accountsById->keys()->all())
            ->groupBy('journal_entry_lines.account_id')
            ->selectRaw('journal_entry_lines.account_id, SUM(journal_entry_lines.debit) as total_debit, SUM(journal_entry_lines.credit) as total_credit')
            ->get();

        /** @var array<int, array{debit: numeric-string, credit: numeric-string}> $totals */
        $totals = [];

        foreach ($rows as $row) {
            $totals[(int) $row->account_id] = [
                'debit' => number_format((float) $row->total_debit, 3, '.', ''),
                'credit' => number_format((float) $row->total_credit, 3, '.', ''),
            ];
        }

        return [$totals, $accountsById];
    }

    /**
     * Normalize one direction's ledger rows and compute its signed total.
     *
     * Collected VAT = credits - debits (sales side credits the VAT account);
     * deductible VAT = debits - credits (purchase side debits it).
     *
     * @param  array<int, array{debit: numeric-string, credit: numeric-string}>  $ledgerByAccountId
     * @param  Collection<int|string, Account>  $accounts
     * @return array{0: numeric-string, 1: list<VatLedgerRow>}
     */
    private function sumLedgerDirection(array $ledgerByAccountId, Collection $accounts, bool $isOutput): array
    {
        $total = '0.000';

        /** @var list<VatLedgerRow> $rows */
        $rows = [];

        foreach ($accounts as $account) {
            $movement = $ledgerByAccountId[$account->id] ?? null;

            $debit = $movement['debit'] ?? '0.000';
            $credit = $movement['credit'] ?? '0.000';

            $rows[] = [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'debit' => $debit,
                'credit' => $credit,
            ];

            $total = $isOutput
                ? bcadd($total, bcsub($credit, $debit, 3), 3)
                : bcadd($total, bcsub($debit, $credit, 3), 3);
        }

        return [$total, $rows];
    }

    /**
     * Fetch posted document lines carrying a tax rate, uniformly shaped.
     *
     * Only the four document families that generate VAT participate:
     * sales invoices / sales credit notes on the output side, purchase
     * invoices / expenses on the input side. Payments are deliberately
     * excluded. Lines without a tax_rate_id have no VAT semantics and are
     * skipped. Documents are scoped by company + fiscal year + POSTED
     * status + document-date window.
     *
     * @return list<VatDetailRow>
     */
    private function fetchDocumentDetails(Company $company, FiscalYear $fiscalYear, string $fromDate, string $toDate): array
    {
        $fromBound = Carbon::parse($fromDate)->format('Y-m-d');
        $toBound = Carbon::parse($toDate)->endOfDay()->format('Y-m-d H:i:s');

        /** @var list<array{sql: string, direction: string}> $queries */
        $queries = [];

        // Placeholder order for every query below: company id, fiscal year
        // id, window lower bound, window upper bound. Document statuses are
        // inlined as literals ('posted').
        $scopeBindings = [$company->id, $fiscalYear->id, $fromBound, $toBound];

        $queries[] = [
            'sql' => <<<'SQL'
                select
                    invoices.invoice_date as row_date,
                    invoices.invoice_number as document_number,
                    customers.name as party,
                    invoice_lines.tax_rate_id as tax_rate_id,
                    invoice_lines.tax_code as snapshot_code,
                    invoice_lines.tax_rate as snapshot_rate,
                    invoice_lines.line_subtotal as base,
                    invoice_lines.tax_amount as vat,
                    tax_rates.code as live_code,
                    tax_rates.type as live_type
                from invoice_lines
                join invoices on invoices.id = invoice_lines.invoice_id
                left join customers on customers.id = invoices.customer_id
                left join tax_rates on tax_rates.id = invoice_lines.tax_rate_id
                where invoices.company_id = ? and invoices.fiscal_year_id = ?
                  and invoices.status = 'posted'
                  and invoices.invoice_date >= ? and invoices.invoice_date <= ?
                  and invoice_lines.tax_rate_id is not null
                SQL,
            'direction' => self::DIRECTION_OUTPUT,
        ];

        $queries[] = [
            'sql' => <<<'SQL'
                select
                    credit_notes.credit_note_date as row_date,
                    credit_notes.credit_note_number as document_number,
                    customers.name as party,
                    credit_note_lines.tax_rate_id as tax_rate_id,
                    credit_note_lines.tax_code as snapshot_code,
                    credit_note_lines.tax_rate as snapshot_rate,
                    credit_note_lines.line_subtotal as base,
                    credit_note_lines.tax_amount as vat,
                    tax_rates.code as live_code,
                    tax_rates.type as live_type
                from credit_note_lines
                join credit_notes on credit_notes.id = credit_note_lines.credit_note_id
                left join customers on customers.id = credit_notes.customer_id
                left join tax_rates on tax_rates.id = credit_note_lines.tax_rate_id
                where credit_notes.company_id = ? and credit_notes.fiscal_year_id = ?
                  and credit_notes.status = 'posted'
                  and credit_notes.credit_note_date >= ? and credit_notes.credit_note_date <= ?
                  and credit_note_lines.tax_rate_id is not null
                SQL,
            'direction' => self::DIRECTION_OUTPUT,
        ];

        $queries[] = [
            'sql' => <<<'SQL'
                select
                    purchase_invoices.invoice_date as row_date,
                    purchase_invoices.invoice_number as document_number,
                    suppliers.name as party,
                    purchase_invoice_lines.tax_rate_id as tax_rate_id,
                    purchase_invoice_lines.tax_code as snapshot_code,
                    purchase_invoice_lines.tax_rate as snapshot_rate,
                    purchase_invoice_lines.line_subtotal as base,
                    purchase_invoice_lines.tax_amount as vat,
                    tax_rates.code as live_code,
                    tax_rates.type as live_type
                from purchase_invoice_lines
                join purchase_invoices on purchase_invoices.id = purchase_invoice_lines.purchase_invoice_id
                left join suppliers on suppliers.id = purchase_invoices.supplier_id
                left join tax_rates on tax_rates.id = purchase_invoice_lines.tax_rate_id
                where purchase_invoices.company_id = ? and purchase_invoices.fiscal_year_id = ?
                  and purchase_invoices.status = 'posted'
                  and purchase_invoices.invoice_date >= ? and purchase_invoices.invoice_date <= ?
                  and purchase_invoice_lines.tax_rate_id is not null
                SQL,
            'direction' => self::DIRECTION_INPUT,
        ];

        $queries[] = [
            'sql' => <<<'SQL'
                select
                    expenses.expense_date as row_date,
                    expenses.expense_number as document_number,
                    suppliers.name as party,
                    expense_lines.tax_rate_id as tax_rate_id,
                    expense_lines.tax_code as snapshot_code,
                    expense_lines.tax_rate as snapshot_rate,
                    expense_lines.line_subtotal as base,
                    expense_lines.tax_amount as vat,
                    tax_rates.code as live_code,
                    tax_rates.type as live_type
                from expense_lines
                join expenses on expenses.id = expense_lines.expense_id
                left join suppliers on suppliers.id = expenses.supplier_id
                left join tax_rates on tax_rates.id = expense_lines.tax_rate_id
                where expenses.company_id = ? and expenses.fiscal_year_id = ?
                  and expenses.status = 'posted'
                  and expenses.expense_date >= ? and expenses.expense_date <= ?
                  and expense_lines.tax_rate_id is not null
                SQL,
            'direction' => self::DIRECTION_INPUT,
        ];

        $labels = [
            self::DIRECTION_OUTPUT.'|invoice' => ['facture_vente', 'Facture de vente'],
            self::DIRECTION_OUTPUT.'|credit_note' => ['avoir_vente', 'Avoir de vente'],
            self::DIRECTION_INPUT.'|purchase_invoice' => ['facture_achat', "Facture d'achat"],
            self::DIRECTION_INPUT.'|expense' => ['depense_fournisseur', 'Dépense fournisseur'],
        ];

        $documentKeys = ['invoice', 'credit_note', 'purchase_invoice', 'expense'];

        /** @var list<VatDetailRow> $details */
        $details = [];

        foreach ($queries as $index => $query) {
            [$documentType, $typeLabel] = $labels[$query['direction'].'|'.$documentKeys[$index]];

            $rawRows = DB::select($query['sql'], $scopeBindings);

            foreach ($rawRows as $rawRow) {
                // Sales credit notes REDUCE collected VAT: their rows are
                // signed negatively so detail rows, rate breakdowns and
                // document-attributed totals all net exactly against the
                // posted ledger movements on the output VAT accounts.
                $sign = $documentType === 'avoir_vente' ? '-1' : '1';

                $details[] = [
                    'date' => Carbon::parse((string) $rawRow->row_date)->format('Y-m-d'),
                    'direction' => $query['direction'],
                    'direction_label' => $query['direction'] === self::DIRECTION_OUTPUT ? 'TVA collectée' : 'TVA déductible',
                    'document_type' => $documentType,
                    'document_type_label' => $typeLabel,
                    'document_number' => (string) $rawRow->document_number,
                    'party' => $rawRow->party !== null ? (string) $rawRow->party : '—',
                    'tax_rate_id' => $rawRow->tax_rate_id !== null ? (int) $rawRow->tax_rate_id : null,
                    'code' => $rawRow->snapshot_code !== null && (string) $rawRow->snapshot_code !== ''
                        ? (string) $rawRow->snapshot_code
                        : (($rawRow->live_code !== null && (string) $rawRow->live_code !== '') ? (string) $rawRow->live_code : '(sans code)'),
                    'rate' => number_format((float) ($rawRow->snapshot_rate ?? 0), 3, '.', ''),
                    'base' => bcmul(number_format((float) $rawRow->base, 3, '.', ''), $sign, 3),
                    'vat' => bcmul(number_format((float) $rawRow->vat, 3, '.', ''), $sign, 3),
                    'type' => $rawRow->live_type !== null ? (string) $rawRow->live_type : null,
                ];
            }
        }

        usort($details, fn (array $a, array $b): int => strcmp($a['date'].$a['document_number'], $b['date'].$b['document_number']));

        return $details;
    }

    /**
     * Sum document VAT and base amounts for one direction.
     *
     * @param  list<VatDetailRow>  $details
     * @return array{0: numeric-string, 1: numeric-string}
     */
    private function sumDocumentDirection(array $details, string $direction): array
    {
        $vatTotal = '0.000';
        $baseTotal = '0.000';

        foreach ($details as $detail) {
            if ($detail['direction'] !== $direction) {
                continue;
            }

            $vatTotal = bcadd($vatTotal, $detail['vat'], 3);
            $baseTotal = bcadd($baseTotal, $detail['base'], 3);
        }

        return [$vatTotal, $baseTotal];
    }

    /**
     * Keep only the details of one direction.
     *
     * @param  list<VatDetailRow>  $details
     * @return list<VatDetailRow>
     */
    private function filterDetailsByDirection(array $details, string $direction): array
    {
        return array_values(array_filter($details, fn (array $detail): bool => $detail['direction'] === $direction));
    }
}
