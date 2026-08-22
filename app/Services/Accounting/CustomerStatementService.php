<?php

namespace App\Services\Accounting;

use App\Enums\CreditNoteStatus;
use App\Enums\CustomerPaymentStatus;
use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Read-only customer statement (Relevé client) built from posted documents.
 *
 * No ledger table is created or written: the statement aggregates POSTED sales
 * invoices (DEBIT), POSTED sales credit notes (CREDIT) and POSTED customer
 * payments (CREDIT) into a chronological account of the customer receivable,
 * mirroring the accounting convention of the receivable account.
 *
 * Ordering (deterministic): document date ASC, then document type priority
 * (invoice → credit note → payment), then document number ASC, then source id ASC.
 *
 * All monetary values are BCMath numeric strings with 3 decimal places.
 */
class CustomerStatementService
{
    private const TYPE_INVOICE = 'invoice';

    private const TYPE_CREDIT_NOTE = 'credit_note';

    private const TYPE_PAYMENT = 'payment';

    /**
     * @var array<string, int>
     */
    private const TYPE_PRIORITIES = [
        self::TYPE_INVOICE => 1,
        self::TYPE_CREDIT_NOTE => 2,
        self::TYPE_PAYMENT => 3,
    ];

    /**
     * Customers selectable for a statement: active customers plus inactive ones
     * that still have historical posted transactions.
     *
     * @return EloquentCollection<int, Customer>
     */
    public function getCustomersForContext(Company $company): EloquentCollection
    {
        /** @var EloquentCollection<int, Customer> */
        return Customer::where('company_id', $company->id)
            ->where(function ($query): void {
                $query->where('is_active', true)
                    ->orWhereHas('invoices', fn ($q) => $q->where('status', InvoiceStatus::POSTED->value))
                    ->orWhereHas('creditNotes', fn ($q) => $q->where('status', CreditNoteStatus::POSTED->value))
                    ->orWhereHas('customerPayments', fn ($q) => $q->where('status', CustomerPaymentStatus::POSTED->value));
            })
            ->orderBy('name')
            ->orderBy('code')
            ->get(['id', 'company_id', 'code', 'name', 'tax_identifier', 'is_active']);
    }

    /**
     * Full statement for one customer over an optional inclusive date range.
     *
     * @param  string|null  $fromDate  inclusive lower bound (Y-m-d)
     * @param  string|null  $toDate  inclusive upper bound (Y-m-d)
     * @return array{customer: Customer, from_date: string|null, to_date: string|null, opening_balance: numeric-string, entries: list<array{date: string, type: string, document_number: string, reference: string|null, description: string|null, debit: numeric-string, credit: numeric-string, balance: numeric-string, source_id: int}>, total_debit: numeric-string, total_credit: numeric-string, closing_balance: numeric-string}
     *
     * @throws \InvalidArgumentException
     */
    public function getStatement(Customer $customer, ?string $fromDate = null, ?string $toDate = null): array
    {
        [$fromDate, $toDate] = $this->normalizeRange($fromDate, $toDate);

        $openingBalance = $this->getOpeningBalance($customer, $fromDate);

        $entries = $this->calculateRunningBalance(
            $this->getStatementEntries($customer, $fromDate, $toDate),
            $openingBalance
        );

        $totalDebit = '0.000';
        $totalCredit = '0.000';

        foreach ($entries as $entry) {
            $totalDebit = bcadd($totalDebit, $entry['debit'], 3);
            $totalCredit = bcadd($totalCredit, $entry['credit'], 3);
        }

        return [
            'customer' => $customer,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'opening_balance' => $openingBalance,
            'entries' => $entries,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'closing_balance' => bcadd($openingBalance, bcsub($totalDebit, $totalCredit, 3), 3),
        ];
    }

    /**
     * Balance carried forward from movements strictly BEFORE the period start;
     * transactions dated exactly on from_date belong to the displayed period.
     *
     * @return numeric-string
     *
     * @throws \InvalidArgumentException
     */
    public function getOpeningBalance(Customer $customer, ?string $fromDate = null): string
    {
        if ($fromDate === null || $fromDate === '') {
            return '0.000';
        }

        $fromDate = $this->normalizeDate($fromDate, 'date de début');

        $debit = $this->sumMovementsBefore($customer, $fromDate, true);
        $credit = $this->sumMovementsBefore($customer, $fromDate, false);

        return bcsub($debit, $credit, 3);
    }

    /**
     * Normalized statement rows for the period, deterministically ordered.
     *
     * Each posted document appears exactly once: a payment is counted once even
     * when allocated to several invoices (allocations are not statement rows).
     *
     * @param  string|null  $fromDate  inclusive (Y-m-d)
     * @param  string|null  $toDate  inclusive (Y-m-d)
     * @return list<array{date: string, type: string, document_number: string, reference: string|null, description: string|null, debit: numeric-string, credit: numeric-string, balance: numeric-string, source_id: int}>
     *
     * @throws \InvalidArgumentException
     */
    public function getStatementEntries(Customer $customer, ?string $fromDate = null, ?string $toDate = null): array
    {
        [$fromDate, $toDate] = $this->normalizeRange($fromDate, $toDate);

        $invoiceQuery = Invoice::query()
            ->select(['id', 'invoice_number', 'invoice_date', 'total'])
            ->where('company_id', $customer->company_id)
            ->where('customer_id', $customer->id)
            ->where('status', InvoiceStatus::POSTED->value);

        $creditNoteQuery = CreditNote::query()
            ->select(['id', 'credit_note_number', 'credit_note_date', 'total', 'reason'])
            ->where('company_id', $customer->company_id)
            ->where('customer_id', $customer->id)
            ->where('status', CreditNoteStatus::POSTED->value);

        $paymentQuery = CustomerPayment::query()
            ->select(['id', 'payment_number', 'payment_date', 'amount', 'reference'])
            ->where('company_id', $customer->company_id)
            ->where('customer_id', $customer->id)
            ->where('status', CustomerPaymentStatus::POSTED->value);

        if ($fromDate !== null) {
            $invoiceQuery->where('invoice_date', '>=', $fromDate);
            $creditNoteQuery->where('credit_note_date', '>=', $fromDate);
            $paymentQuery->where('payment_date', '>=', $fromDate);
        }

        if ($toDate !== null) {
            // Strictly-below exclusive upper bound: document dates are stored
            // as timestamps, so an inclusive "<= toDate" string comparison
            // would wrongly exclude documents dated exactly on toDate.
            $exclusiveToDate = $this->exclusiveUpperBound($toDate);
            $invoiceQuery->where('invoice_date', '<', $exclusiveToDate);
            $creditNoteQuery->where('credit_note_date', '<', $exclusiveToDate);
            $paymentQuery->where('payment_date', '<', $exclusiveToDate);
        }

        /** @var list<array{date: string, type: string, document_number: string, reference: string|null, description: string|null, debit: numeric-string, credit: numeric-string, balance: numeric-string, source_id: int}> $rows */
        $rows = [];

        foreach ($invoiceQuery->get() as $invoice) {
            $rows[] = [
                'date' => $invoice->invoice_date->toDateString(),
                'type' => self::TYPE_INVOICE,
                'document_number' => $invoice->invoice_number,
                'reference' => null,
                'description' => null,
                'debit' => $this->normalizeDecimal((string) $invoice->total),
                'credit' => '0.000',
                'balance' => '0.000',
                'source_id' => (int) $invoice->id,
            ];
        }

        foreach ($creditNoteQuery->get() as $creditNote) {
            $rows[] = [
                'date' => $creditNote->credit_note_date->toDateString(),
                'type' => self::TYPE_CREDIT_NOTE,
                'document_number' => $creditNote->credit_note_number,
                'reference' => $creditNote->reason,
                'description' => $creditNote->reason,
                'debit' => '0.000',
                'credit' => $this->normalizeDecimal((string) $creditNote->total),
                'balance' => '0.000',
                'source_id' => (int) $creditNote->id,
            ];
        }

        foreach ($paymentQuery->get() as $payment) {
            $rows[] = [
                'date' => $payment->payment_date->toDateString(),
                'type' => self::TYPE_PAYMENT,
                'document_number' => $payment->payment_number,
                'reference' => $payment->reference,
                'description' => null,
                'debit' => '0.000',
                'credit' => $this->normalizeDecimal((string) $payment->amount),
                'balance' => '0.000',
                'source_id' => (int) $payment->id,
            ];
        }

        usort($rows, fn (array $a, array $b): int => $this->compareRows($a, $b));

        return $rows;
    }

    /**
     * Apply the running balance to ordered entries: previous balance + debit − credit.
     *
     * Returns the same rows (order unchanged) with their running balance filled in.
     *
     * @param  list<array{date: string, type: string, document_number: string, reference: string|null, description: string|null, debit: numeric-string, credit: numeric-string, balance: numeric-string, source_id: int}>  $entries
     * @param  numeric-string  $openingBalance
     * @return list<array{date: string, type: string, document_number: string, reference: string|null, description: string|null, debit: numeric-string, credit: numeric-string, balance: numeric-string, source_id: int}>
     */
    public function calculateRunningBalance(array $entries, string $openingBalance): array
    {
        $runningBalance = $this->normalizeDecimal($openingBalance);

        foreach ($entries as $index => $entry) {
            $runningBalance = bcadd(
                bcsub($runningBalance, $entry['credit'], 3),
                $entry['debit'],
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
    public function getSummary(Customer $customer, ?string $fromDate = null, ?string $toDate = null): array
    {
        [$fromDate, $toDate] = $this->normalizeRange($fromDate, $toDate);

        $openingBalance = $this->getOpeningBalance($customer, $fromDate);

        $totalDebit = $this->aggregateColumn($customer, self::TYPE_INVOICE, 'total', $fromDate, $toDate);
        $totalCredit = bcadd(
            $this->aggregateColumn($customer, self::TYPE_CREDIT_NOTE, 'total', $fromDate, $toDate),
            $this->aggregateColumn($customer, self::TYPE_PAYMENT, 'amount', $fromDate, $toDate),
            3
        );

        return [
            'opening_balance' => $openingBalance,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'closing_balance' => bcadd($openingBalance, bcsub($totalDebit, $totalCredit, 3), 3),
        ];
    }

    /**
     * All-time outstanding balance: posted invoices − posted credit notes −
     * posted payments, each payment counted once regardless of allocations.
     *
     * Positive means the customer owes the company.
     *
     * @return numeric-string
     */
    public function getOutstandingBalance(Customer $customer): string
    {
        $summary = $this->getSummary($customer);

        return $summary['closing_balance'];
    }

    /**
     * Sum of posted movements strictly BEFORE a date, on the debit (invoices)
     * or credit (credit notes + payments) side of the customer receivable.
     *
     * @return numeric-string
     */
    private function sumMovementsBefore(Customer $customer, string $beforeDate, bool $debitSide): string
    {
        if ($debitSide) {
            return $this->decimalFromDb(
                Invoice::query()
                    ->where('company_id', $customer->company_id)
                    ->where('customer_id', $customer->id)
                    ->where('status', InvoiceStatus::POSTED->value)
                    ->where('invoice_date', '<', $beforeDate)
                    ->sum('total')
            );
        }

        $creditNotes = $this->decimalFromDb(
            CreditNote::query()
                ->where('company_id', $customer->company_id)
                ->where('customer_id', $customer->id)
                ->where('status', CreditNoteStatus::POSTED->value)
                ->where('credit_note_date', '<', $beforeDate)
                ->sum('total')
        );

        $payments = $this->decimalFromDb(
            CustomerPayment::query()
                ->where('company_id', $customer->company_id)
                ->where('customer_id', $customer->id)
                ->where('status', CustomerPaymentStatus::POSTED->value)
                ->where('payment_date', '<', $beforeDate)
                ->sum('amount')
        );

        return bcadd($creditNotes, $payments, 3);
    }

    /**
     * Aggregate a monetary column over POSTED documents of one type within an
     * inclusive [fromDate, toDate] range; null bounds are open.
     *
     * @return numeric-string
     */
    private function aggregateColumn(Customer $customer, string $type, string $column, ?string $fromDate, ?string $toDate): string
    {
        $query = match ($type) {
            self::TYPE_INVOICE => Invoice::query(),
            self::TYPE_CREDIT_NOTE => CreditNote::query(),
            self::TYPE_PAYMENT => CustomerPayment::query(),
            default => throw new \InvalidArgumentException(sprintf('Type de document inconnu : %s.', $type)),
        };

        return $this->decimalFromDb(
            $query
                ->where('company_id', $customer->company_id)
                ->where('customer_id', $customer->id)
                ->where('status', $this->postedStatusValue($type))
                ->when($fromDate !== null, fn ($q) => $q->where($this->dateColumnName($type), '>=', $fromDate))
                ->when($toDate !== null, fn ($q) => $q->where($this->dateColumnName($type), '<', $this->exclusiveUpperBound($toDate)))
                ->sum($column)
        );
    }

    private function postedStatusValue(string $type): string
    {
        return match ($type) {
            self::TYPE_INVOICE => InvoiceStatus::POSTED->value,
            self::TYPE_CREDIT_NOTE => CreditNoteStatus::POSTED->value,
            self::TYPE_PAYMENT => CustomerPaymentStatus::POSTED->value,
            default => throw new \InvalidArgumentException(sprintf('Type de document inconnu : %s.', $type)),
        };
    }

    private function dateColumnName(string $type): string
    {
        return match ($type) {
            self::TYPE_INVOICE => 'invoice_date',
            self::TYPE_CREDIT_NOTE => 'credit_note_date',
            self::TYPE_PAYMENT => 'payment_date',
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
     * @param  array{date: string, type: string, document_number: string, reference: string|null, description: string|null, debit: numeric-string, credit: numeric-string, balance: numeric-string, source_id: int}  $a
     * @param  array{date: string, type: string, document_number: string, reference: string|null, description: string|null, debit: numeric-string, credit: numeric-string, balance: numeric-string, source_id: int}  $b
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
