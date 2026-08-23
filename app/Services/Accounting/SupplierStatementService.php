<?php

namespace App\Services\Accounting;

use App\Enums\PurchaseInvoiceStatus;
use App\Enums\SupplierPaymentStatus;
use App\Models\Company;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Read-only supplier statement (Relevé fournisseur) built from posted documents.
 *
 * No ledger table is created or written: the statement aggregates POSTED
 * purchase invoices and POSTED supplier payments into a chronological account
 * of the supplier payable, mirroring the accounting convention of the payable
 * account — reversed relative to the customer receivable:
 *
 *   - Purchase invoice → CREDIT movement on the payable account,
 *   - Supplier payment → DEBIT movement on the payable account.
 *
 * A positive balance therefore means the company still owes the supplier.
 * Supplier credit notes do not exist yet; when they are implemented they will
 * add a DEBIT side document to this statement.
 *
 * Ordering (deterministic): document date ASC, then document type priority
 * (purchase invoice → supplier payment), then document number ASC, then source id ASC.
 *
 * All monetary values are BCMath numeric strings with 3 decimal places.
 */
class SupplierStatementService
{
    private const TYPE_PURCHASE_INVOICE = 'purchase_invoice';

    private const TYPE_SUPPLIER_PAYMENT = 'supplier_payment';

    /**
     * @var array<string, int>
     */
    private const TYPE_PRIORITIES = [
        self::TYPE_PURCHASE_INVOICE => 1,
        self::TYPE_SUPPLIER_PAYMENT => 2,
    ];

    /**
     * Suppliers selectable for a statement: active suppliers plus inactive ones
     * that still have historical posted transactions.
     *
     * @return EloquentCollection<int, Supplier>
     */
    public function getSuppliersForContext(Company $company): EloquentCollection
    {
        /** @var EloquentCollection<int, Supplier> */
        return Supplier::where('company_id', $company->id)
            ->where(function ($query): void {
                $query->where('is_active', true)
                    ->orWhereHas('purchaseInvoices', fn ($q) => $q->where('status', PurchaseInvoiceStatus::POSTED->value))
                    ->orWhereHas('supplierPayments', fn ($q) => $q->where('status', SupplierPaymentStatus::POSTED->value));
            })
            ->orderBy('name')
            ->orderBy('code')
            ->get(['id', 'company_id', 'code', 'name', 'tax_identifier', 'is_active']);
    }

    /**
     * Full statement for one supplier over an optional inclusive date range.
     *
     * @param  string|null  $fromDate  inclusive lower bound (Y-m-d)
     * @param  string|null  $toDate  inclusive upper bound (Y-m-d)
     * @return array{supplier: Supplier, from_date: string|null, to_date: string|null, opening_balance: numeric-string, entries: list<array{date: string, type: string, document_number: string, supplier_invoice_number: string|null, reference: string|null, description: string|null, debit: numeric-string, credit: numeric-string, balance: numeric-string, source_id: int}>, total_debit: numeric-string, total_credit: numeric-string, closing_balance: numeric-string}
     *
     * @throws \InvalidArgumentException
     */
    public function getStatement(Supplier $supplier, ?string $fromDate = null, ?string $toDate = null): array
    {
        [$fromDate, $toDate] = $this->normalizeRange($fromDate, $toDate);

        $openingBalance = $this->getOpeningBalance($supplier, $fromDate);

        $entries = $this->calculateRunningBalance(
            $this->getStatementEntries($supplier, $fromDate, $toDate),
            $openingBalance
        );

        $totalDebit = '0.000';
        $totalCredit = '0.000';

        foreach ($entries as $entry) {
            $totalDebit = bcadd($totalDebit, $entry['debit'], 3);
            $totalCredit = bcadd($totalCredit, $entry['credit'], 3);
        }

        return [
            'supplier' => $supplier,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'opening_balance' => $openingBalance,
            'entries' => $entries,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'closing_balance' => bcadd($openingBalance, bcsub($totalCredit, $totalDebit, 3), 3),
        ];
    }

    /**
     * Balance carried forward from movements strictly BEFORE the period start;
     * transactions dated exactly on from_date belong to the displayed period.
     *
     * On the payable account the opening balance is credits (invoices) minus
     * debits (payments): positive means money already owed before the period.
     *
     * @return numeric-string
     *
     * @throws \InvalidArgumentException
     */
    public function getOpeningBalance(Supplier $supplier, ?string $fromDate = null): string
    {
        if ($fromDate === null || $fromDate === '') {
            return '0.000';
        }

        $fromDate = $this->normalizeDate($fromDate, 'date de début');

        $credits = $this->sumMovementsBefore($supplier, $fromDate, true);
        $debits = $this->sumMovementsBefore($supplier, $fromDate, false);

        return bcsub($credits, $debits, 3);
    }

    /**
     * Normalized statement rows for the period, deterministically ordered.
     *
     * Each posted document appears exactly once: a payment is counted once even
     * when allocated to several purchase invoices (allocations are not
     * statement rows).
     *
     * @param  string|null  $fromDate  inclusive (Y-m-d)
     * @param  string|null  $toDate  inclusive (Y-m-d)
     * @return list<array{date: string, type: string, document_number: string, supplier_invoice_number: string|null, reference: string|null, description: string|null, debit: numeric-string, credit: numeric-string, balance: numeric-string, source_id: int}>
     *
     * @throws \InvalidArgumentException
     */
    public function getStatementEntries(Supplier $supplier, ?string $fromDate = null, ?string $toDate = null): array
    {
        [$fromDate, $toDate] = $this->normalizeRange($fromDate, $toDate);

        $invoiceQuery = PurchaseInvoice::query()
            ->select(['id', 'invoice_number', 'supplier_invoice_number', 'invoice_date', 'total'])
            ->where('company_id', $supplier->company_id)
            ->where('supplier_id', $supplier->id)
            ->where('status', PurchaseInvoiceStatus::POSTED->value);

        $paymentQuery = SupplierPayment::query()
            ->select(['id', 'payment_number', 'payment_date', 'amount', 'reference'])
            ->where('company_id', $supplier->company_id)
            ->where('supplier_id', $supplier->id)
            ->where('status', SupplierPaymentStatus::POSTED->value);

        if ($fromDate !== null) {
            $invoiceQuery->where('invoice_date', '>=', $fromDate);
            $paymentQuery->where('payment_date', '>=', $fromDate);
        }

        if ($toDate !== null) {
            // Strictly-below exclusive upper bound: document dates are stored
            // as timestamps, so an inclusive "<= toDate" string comparison
            // would wrongly exclude documents dated exactly on toDate.
            $exclusiveToDate = $this->exclusiveUpperBound($toDate);
            $invoiceQuery->where('invoice_date', '<', $exclusiveToDate);
            $paymentQuery->where('payment_date', '<', $exclusiveToDate);
        }

        /** @var list<array{date: string, type: string, document_number: string, supplier_invoice_number: string|null, reference: string|null, description: string|null, debit: numeric-string, credit: numeric-string, balance: numeric-string, source_id: int}> $rows */
        $rows = [];

        foreach ($invoiceQuery->get() as $invoice) {
            $rows[] = [
                'date' => $invoice->invoice_date->toDateString(),
                'type' => self::TYPE_PURCHASE_INVOICE,
                'document_number' => $invoice->invoice_number,
                'supplier_invoice_number' => $invoice->supplier_invoice_number,
                'reference' => null,
                'description' => null,
                'debit' => '0.000',
                'credit' => $this->normalizeDecimal((string) $invoice->total),
                'balance' => '0.000',
                'source_id' => (int) $invoice->id,
            ];
        }

        foreach ($paymentQuery->get() as $payment) {
            $rows[] = [
                'date' => $payment->payment_date->toDateString(),
                'type' => self::TYPE_SUPPLIER_PAYMENT,
                'document_number' => $payment->payment_number,
                'supplier_invoice_number' => null,
                'reference' => $payment->reference,
                'description' => null,
                'debit' => $this->normalizeDecimal((string) $payment->amount),
                'credit' => '0.000',
                'balance' => '0.000',
                'source_id' => (int) $payment->id,
            ];
        }

        usort($rows, fn (array $a, array $b): int => $this->compareRows($a, $b));

        return $rows;
    }

    /**
     * Apply the running balance to ordered entries: previous balance + credit − debit.
     *
     * Returns the same rows (order unchanged) with their running balance filled in.
     *
     * @param  list<array{date: string, type: string, document_number: string, supplier_invoice_number: string|null, reference: string|null, description: string|null, debit: numeric-string, credit: numeric-string, balance: numeric-string, source_id: int}>  $entries
     * @param  numeric-string  $openingBalance
     * @return list<array{date: string, type: string, document_number: string, supplier_invoice_number: string|null, reference: string|null, description: string|null, debit: numeric-string, credit: numeric-string, balance: numeric-string, source_id: int}>
     */
    public function calculateRunningBalance(array $entries, string $openingBalance): array
    {
        $runningBalance = $this->normalizeDecimal($openingBalance);

        foreach ($entries as $index => $entry) {
            $runningBalance = bcadd(
                bcsub($runningBalance, $entry['debit'], 3),
                $entry['credit'],
                3
            );

            $entries[$index]['balance'] = $runningBalance;
        }

        return $entries;
    }

    /**
     * Period summary computed with database aggregates (never loads rows).
     *
     * @return array{opening_balance: numeric-string, total_debit: numeric-string, total_credit: numeric-string, closing_balance: numeric-string}
     *
     * @throws \InvalidArgumentException
     */
    public function getSummary(Supplier $supplier, ?string $fromDate = null, ?string $toDate = null): array
    {
        [$fromDate, $toDate] = $this->normalizeRange($fromDate, $toDate);

        $openingBalance = $this->getOpeningBalance($supplier, $fromDate);

        $totalDebit = $this->aggregateColumn($supplier, self::TYPE_SUPPLIER_PAYMENT, 'amount', $fromDate, $toDate);
        $totalCredit = $this->aggregateColumn($supplier, self::TYPE_PURCHASE_INVOICE, 'total', $fromDate, $toDate);

        return [
            'opening_balance' => $openingBalance,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'closing_balance' => bcadd($openingBalance, bcsub($totalCredit, $totalDebit, 3), 3),
        ];
    }

    /**
     * All-time outstanding balance: posted purchase invoices − posted supplier
     * payments, each payment counted once regardless of allocations.
     *
     * Positive means the company owes the supplier. Supplier credit notes do
     * not exist yet; when implemented they will be subtracted here as well.
     *
     * @return numeric-string
     */
    public function getOutstandingBalance(Supplier $supplier): string
    {
        $summary = $this->getSummary($supplier);

        return $summary['closing_balance'];
    }

    /**
     * Sum of posted movements strictly BEFORE a date, on the credit (purchase
     * invoices) or debit (supplier payments) side of the supplier payable.
     *
     * @return numeric-string
     */
    private function sumMovementsBefore(Supplier $supplier, string $beforeDate, bool $creditSide): string
    {
        if ($creditSide) {
            return $this->decimalFromDb(
                PurchaseInvoice::query()
                    ->where('company_id', $supplier->company_id)
                    ->where('supplier_id', $supplier->id)
                    ->where('status', PurchaseInvoiceStatus::POSTED->value)
                    ->where('invoice_date', '<', $beforeDate)
                    ->sum('total')
            );
        }

        return $this->decimalFromDb(
            SupplierPayment::query()
                ->where('company_id', $supplier->company_id)
                ->where('supplier_id', $supplier->id)
                ->where('status', SupplierPaymentStatus::POSTED->value)
                ->where('payment_date', '<', $beforeDate)
                ->sum('amount')
        );
    }

    /**
     * Aggregate a monetary column over POSTED documents of one type within an
     * inclusive [fromDate, toDate] range; null bounds are open.
     *
     * @return numeric-string
     */
    private function aggregateColumn(Supplier $supplier, string $type, string $column, ?string $fromDate, ?string $toDate): string
    {
        $query = match ($type) {
            self::TYPE_PURCHASE_INVOICE => PurchaseInvoice::query(),
            self::TYPE_SUPPLIER_PAYMENT => SupplierPayment::query(),
            default => throw new \InvalidArgumentException(sprintf('Type de document inconnu : %s.', $type)),
        };

        return $this->decimalFromDb(
            $query
                ->where('company_id', $supplier->company_id)
                ->where('supplier_id', $supplier->id)
                ->where('status', $this->postedStatusValue($type))
                ->when($fromDate !== null, fn ($q) => $q->where($this->dateColumnName($type), '>=', $fromDate))
                ->when($toDate !== null, fn ($q) => $q->where($this->dateColumnName($type), '<', $this->exclusiveUpperBound($toDate)))
                ->sum($column)
        );
    }

    private function postedStatusValue(string $type): string
    {
        return match ($type) {
            self::TYPE_PURCHASE_INVOICE => PurchaseInvoiceStatus::POSTED->value,
            self::TYPE_SUPPLIER_PAYMENT => SupplierPaymentStatus::POSTED->value,
            default => throw new \InvalidArgumentException(sprintf('Type de document inconnu : %s.', $type)),
        };
    }

    private function dateColumnName(string $type): string
    {
        return match ($type) {
            self::TYPE_PURCHASE_INVOICE => 'invoice_date',
            self::TYPE_SUPPLIER_PAYMENT => 'payment_date',
            default => throw new \InvalidArgumentException(sprintf('Type de document inconnu : %s.', $type)),
        };
    }

    /**
     * Convert an inclusive Y-m-d upper bound into the exclusive bound used in
     * queries (the next day), so timestamps on the boundary day are included.
     */
    private function exclusiveUpperBound(string $toDate): string
    {
        return CarbonImmutable::parse($toDate)->addDay()->toDateString();
    }

    /**
     * Deterministic row comparison: date, then type priority, then document number, then source id.
     *
     * @param  array{date: string, type: string, document_number: string, supplier_invoice_number: string|null, reference: string|null, description: string|null, debit: numeric-string, credit: numeric-string, balance: numeric-string, source_id: int}  $a
     * @param  array{date: string, type: string, document_number: string, supplier_invoice_number: string|null, reference: string|null, description: string|null, debit: numeric-string, credit: numeric-string, balance: numeric-string, source_id: int}  $b
     */
    private function compareRows(array $a, array $b): int
    {
        $byDate = strcmp($a['date'], $b['date']);
        if ($byDate !== 0) {
            return $byDate;
        }

        $priorityA = self::TYPE_PRIORITIES[$a['type']];
        $priorityB = self::TYPE_PRIORITIES[$b['type']];
        if ($priorityA !== $priorityB) {
            return $priorityA <=> $priorityB;
        }

        $byNumber = strcmp($a['document_number'], $b['document_number']);
        if ($byNumber !== 0) {
            return $byNumber;
        }

        return $a['source_id'] <=> $b['source_id'];
    }

    /**
     * Validate and normalize an optional Y-m-d date string.
     *
     * @throws \InvalidArgumentException
     */
    private function normalizeDate(?string $date, string $label): ?string
    {
        if ($date === null || trim($date) === '') {
            return null;
        }

        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', trim($date));

        if ($parsed === false || $parsed->format('Y-m-d') !== trim($date)) {
            throw new \InvalidArgumentException(sprintf('La %s fournie est invalide.', $label));
        }

        return $parsed->format('Y-m-d');
    }

    /**
     * Validate the optional [fromDate, toDate] range.
     *
     * @return array{string|null, string|null}
     *
     * @throws \InvalidArgumentException
     */
    private function normalizeRange(?string $fromDate, ?string $toDate): array
    {
        $fromDate = $this->normalizeDate($fromDate, 'date de début');
        $toDate = $this->normalizeDate($toDate, 'date de fin');

        if ($fromDate !== null && $toDate !== null && $fromDate > $toDate) {
            throw new \InvalidArgumentException('La date de début doit être antérieure ou égale à la date de fin.');
        }

        return [$fromDate, $toDate];
    }

    /**
     * Normalize a raw database aggregate to a 3-decimal numeric string.
     *
     * @return numeric-string
     */
    private function decimalFromDb(int|float|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '0.000';
        }

        return number_format((float) $value, 3, '.', '');
    }

    /**
     * Normalize a value to a numeric string with 3 decimal places.
     *
     * @return numeric-string
     */
    private function normalizeDecimal(string|int|float|null $value): string
    {
        if ($value === null || $value === '') {
            return '0.000';
        }

        $numVal = is_numeric((string) $value) ? (string) $value : '0.000';

        return number_format((float) $numVal, 3, '.', '');
    }
}
