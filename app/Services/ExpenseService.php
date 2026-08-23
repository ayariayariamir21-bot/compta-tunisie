<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\ExpenseStatus;
use App\Enums\JournalType;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\CompanyAccountingSetting;
use App\Models\Expense;
use App\Models\ExpenseLine;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Services\Accounting\ExpensePostingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function __construct(
        private ExpensePostingService $postingService,
    ) {}

    /**
     * @param  array{company_id: int, supplier_id?: int|null, fiscal_year_id: int, accounting_period_id: int, journal_id: int, payment_method_id?: int|null, expense_date: string, due_date?: string|null, reference?: string|null, description?: string|null, notes?: string|null, created_by: int, lines: array<int, array{expense_account_id: int, label: string, quantity?: string|null, unit_price: string, discount_percent: string, tax_rate_id?: int|null}>}  $data
     */
    public function createDraft(array $data): Expense
    {
        $companyId = $data['company_id'];

        if (! empty($data['supplier_id'])) {
            $this->validateSupplier((int) $data['supplier_id'], $companyId);
        }

        $this->validateMiscJournal((int) $data['journal_id'], $companyId);
        $this->validateFiscalYear((int) $data['fiscal_year_id'], $companyId);
        $this->validateAccountingPeriod((int) $data['accounting_period_id'], (int) $data['fiscal_year_id']);
        $this->validatePeriodOpen((int) $data['accounting_period_id']);
        $this->validateExpenseDateInPeriod($data['expense_date'], (int) $data['accounting_period_id']);
        $this->validateLines($data['lines'], $companyId, (int) $data['fiscal_year_id']);

        $data['expense_number'] = $this->generateExpenseNumber($companyId);
        $data['status'] = ExpenseStatus::DRAFT;
        $data['currency'] = $this->resolveCurrency($companyId);

        if (($data['due_date'] ?? null) === '') {
            $data['due_date'] = null;
        }

        $linesData = $data['lines'];
        unset($data['lines']);

        return DB::transaction(function () use ($data, $linesData, $companyId) {
            $expense = Expense::create($data);

            $this->syncLines($expense, $linesData, $companyId, (int) $data['fiscal_year_id']);
            $this->calculateTotals($expense);

            return $expense->fresh(['lines.expenseAccount', 'lines.taxRate', 'supplier', 'paymentMethod']);
        });
    }

    /**
     * @param  array{supplier_id?: int|null, journal_id: int, payment_method_id?: int|null, expense_date: string, due_date?: string|null, reference?: string|null, description?: string|null, notes?: string|null, lines: array<int, array{expense_account_id: int, label: string, quantity?: string|null, unit_price: string, discount_percent: string, tax_rate_id?: int|null}>}  $data
     */
    public function updateDraft(Expense $expense, array $data): Expense
    {
        if ($expense->status !== ExpenseStatus::DRAFT) {
            throw new \InvalidArgumentException('Seule une dépense en brouillon peut être modifiée.');
        }

        $companyId = $expense->company_id;

        if (! empty($data['supplier_id'])) {
            $this->validateSupplier((int) $data['supplier_id'], $companyId);
        }

        $this->validateMiscJournal((int) $data['journal_id'], $companyId);
        $this->validateLines($data['lines'], $companyId, $expense->fiscal_year_id);

        if (($data['due_date'] ?? null) === '') {
            $data['due_date'] = null;
        }

        $linesData = $data['lines'];
        unset($data['lines']);

        return DB::transaction(function () use ($expense, $data, $linesData, $companyId) {
            $expense->update($data);
            $this->syncLines($expense, $linesData, $companyId, $expense->fiscal_year_id);
            $this->calculateTotals($expense);

            return $expense->fresh(['lines.expenseAccount', 'lines.taxRate', 'supplier', 'paymentMethod']);
        });
    }

    public function post(Expense $expense, int $userId): Expense
    {
        return $this->postingService->post($expense, $userId);
    }

    public function cancel(Expense $expense): Expense
    {
        if ($expense->status !== ExpenseStatus::DRAFT) {
            throw new \InvalidArgumentException('Seule une dépense en brouillon peut être annulée.');
        }

        $expense->update(['status' => ExpenseStatus::CANCELLED]);

        return $expense->fresh();
    }

    public function deleteDraft(Expense $expense): void
    {
        if ($expense->status !== ExpenseStatus::DRAFT) {
            throw new \InvalidArgumentException('Seule une dépense en brouillon peut être supprimée.');
        }

        DB::transaction(function () use ($expense) {
            $expense->lines()->delete();
            $expense->delete();
        });
    }

    public function generateExpenseNumber(int $companyId): string
    {
        $year = (int) now()->year;
        $prefix = "DEP-{$year}-";
        $maxTries = 10;

        $maxNumber = Expense::where('company_id', $companyId)
            ->where('expense_number', 'like', $prefix.'%')
            ->pluck('expense_number')
            ->map(fn (string $num) => (int) substr($num, strlen($prefix)))
            ->filter(fn (int $num) => $num > 0)
            ->max() ?? 0;

        for ($attempt = 0; $attempt < $maxTries; $attempt++) {
            $maxNumber++;
            $candidate = $prefix.str_pad((string) $maxNumber, 6, '0', STR_PAD_LEFT);
            if (! Expense::where('company_id', $companyId)->where('expense_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Impossible de générer un numéro de dépense unique après '.$maxTries.' tentatives.');
    }

    /**
     * Compute all monetary values of a line with BCmath at 3 decimals.
     *
     * A null/empty quantity is treated as 1.
     *
     * @return array{quantity: numeric-string, unit_price: numeric-string, discount_percent: numeric-string, gross_amount: numeric-string, discount_amount: numeric-string, tax_rate_value: numeric-string, tax_amount: numeric-string, line_subtotal: numeric-string, line_total: numeric-string}
     */
    public function calculateLine(?string $quantity, string $unitPrice, string $discountPercent, ?TaxRate $taxRate): array
    {
        bcscale(3);

        $rawQty = trim((string) $quantity);
        $qty = ($rawQty !== '') ? $this->toDecimal($rawQty) : '1';
        $price = $this->toDecimal($unitPrice);
        $discountPct = $this->toDecimal($discountPercent);

        $gross = bcmul($qty, $price, 3);
        $discountAmount = bcdiv(bcmul($gross, $discountPct, 3), '100', 3);
        $lineSubtotal = bcsub($gross, $discountAmount, 3);

        $taxRateValue = $taxRate ? $this->toDecimal((string) $taxRate->rate) : '0';
        $taxAmount = bcdiv(bcmul($lineSubtotal, $taxRateValue, 3), '100', 3);
        $lineTotal = bcadd($lineSubtotal, $taxAmount, 3);

        return [
            'quantity' => $qty,
            'unit_price' => $price,
            'discount_percent' => $discountPct,
            'gross_amount' => $gross,
            'discount_amount' => $discountAmount,
            'tax_rate_value' => $taxRateValue,
            'tax_amount' => $taxAmount,
            'line_subtotal' => $lineSubtotal,
            'line_total' => $lineTotal,
        ];
    }

    public function calculateTotals(Expense $expense): Expense
    {
        $lines = $expense->lines()->get();

        $subtotal = '0';
        $discountTotal = '0';
        $taxTotal = '0';
        $total = '0';

        foreach ($lines as $line) {
            $subtotal = bcadd($subtotal, (string) $line->line_subtotal, 3);
            $discountTotal = bcadd($discountTotal, (string) $line->discount_amount, 3);
            $taxTotal = bcadd($taxTotal, (string) $line->tax_amount, 3);
            $total = bcadd($total, (string) $line->line_total, 3);
        }

        $expense->update([
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'total' => $total,
        ]);

        return $expense->fresh();
    }

    public function validateSupplier(int $supplierId, int $companyId): Supplier
    {
        $supplier = Supplier::where('id', $supplierId)
            ->where('company_id', $companyId)
            ->first();

        if (! $supplier) {
            throw new \InvalidArgumentException('Le fournisseur sélectionné n\'appartient pas à cette société.');
        }

        if (! $supplier->is_active) {
            throw new \InvalidArgumentException('Le fournisseur sélectionné est inactif. Veuillez choisir un fournisseur actif.');
        }

        return $supplier;
    }

    public function validateTaxRate(int $taxRateId, int $companyId): TaxRate
    {
        $taxRate = TaxRate::where('id', $taxRateId)
            ->where('company_id', $companyId)
            ->first();

        if (! $taxRate) {
            throw new \InvalidArgumentException('Le taux de TVA sélectionné n\'appartient pas à cette société.');
        }

        if (! $taxRate->is_active) {
            throw new \InvalidArgumentException('Le taux de TVA sélectionné est inactif.');
        }

        return $taxRate;
    }

    public function validateMiscJournal(int $journalId, int $companyId): Journal
    {
        $journal = Journal::where('id', $journalId)
            ->where('company_id', $companyId)
            ->first();

        if (! $journal) {
            throw new \InvalidArgumentException('Le journal sélectionné n\'appartient pas à cette société.');
        }

        if (! $journal->is_active) {
            throw new \InvalidArgumentException('Le journal sélectionné est inactif.');
        }

        if ($journal->type !== JournalType::OPERATIONS_DIVERSES) {
            throw new \InvalidArgumentException('Le journal sélectionné n\'est pas un journal d\'opérations diverses.');
        }

        return $journal;
    }

    public function validateFiscalYear(int $fiscalYearId, int $companyId): FiscalYear
    {
        $fy = FiscalYear::where('id', $fiscalYearId)
            ->where('company_id', $companyId)
            ->first();

        if (! $fy) {
            throw new \InvalidArgumentException('L\'exercice comptable sélectionné n\'appartient pas à cette société.');
        }

        if ($fy->is_closed) {
            throw new \InvalidArgumentException('L\'exercice comptable est clôturé.');
        }

        return $fy;
    }

    public function validateAccountingPeriod(int $periodId, int $fiscalYearId): AccountingPeriod
    {
        $period = AccountingPeriod::where('id', $periodId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->first();

        if (! $period) {
            throw new \InvalidArgumentException('La période comptable sélectionnée n\'appartient pas à cet exercice.');
        }

        return $period;
    }

    public function validatePeriodOpen(int $periodId): void
    {
        $period = AccountingPeriod::findOrFail($periodId);

        if ($period->is_closed || ! $period->is_open) {
            throw new \InvalidArgumentException('La période comptable est clôturée.');
        }
    }

    public function validateExpenseDateInPeriod(string $expenseDate, int $periodId): void
    {
        $period = AccountingPeriod::findOrFail($periodId);
        $date = Carbon::parse($expenseDate);

        $startDate = Carbon::parse($period->start_date);
        $endDate = Carbon::parse($period->end_date);

        if ($date->lt($startDate) || $date->gt($endDate)) {
            throw new \InvalidArgumentException('La date de la dépense doit être comprise dans la période comptable (du '.$startDate->format('d/m/Y').' au '.$endDate->format('d/m/Y').').');
        }
    }

    public function validateExpenseAccount(int $accountId, int $companyId, int $fiscalYearId): Account
    {
        $account = Account::where('id', $accountId)
            ->where('company_id', $companyId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->first();

        if (! $account) {
            throw new \InvalidArgumentException('Le compte de charge sélectionné n\'appartient pas à cette société/exercice.');
        }

        if (! $account->is_active) {
            throw new \InvalidArgumentException('Le compte de charge sélectionné est inactif.');
        }

        $isExpenseAccount = Account::where('id', $accountId)
            ->where('account_type', AccountType::Expense->value)
            ->exists();

        if (! $isExpenseAccount) {
            throw new \InvalidArgumentException('Le compte sélectionné « '.$account->code.' » n\'est pas un compte de charges. Veuillez choisir un compte de classe charges.');
        }

        return $account;
    }

    /**
     * @param  array<int, array{expense_account_id: int, label: string, quantity?: string|null, unit_price: string, discount_percent: string, tax_rate_id?: int|null}>  $linesData
     */
    public function validateLines(array $linesData, int $companyId, int $fiscalYearId): void
    {
        if (count($linesData) === 0) {
            throw new \InvalidArgumentException('La dépense doit contenir au moins une ligne.');
        }

        foreach ($linesData as $index => $lineData) {
            $label = trim($lineData['label']);

            if ($label === '') {
                throw new \InvalidArgumentException('Chaque ligne doit avoir un libellé (ligne '.($index + 1).').');
            }

            $price = $this->toDecimal($lineData['unit_price']);
            $discount = $this->toDecimal($lineData['discount_percent']);

            if (bccomp($price, '0', 3) < 0) {
                throw new \InvalidArgumentException('Le prix unitaire ne peut pas être négatif (ligne '.($index + 1).').');
            }

            if (bccomp($discount, '0', 3) < 0 || bccomp($discount, '100', 3) > 0) {
                throw new \InvalidArgumentException('Le pourcentage de remise doit être compris entre 0 et 100 (ligne '.($index + 1).').');
            }

            $quantity = trim((string) ($lineData['quantity'] ?? ''));
            if ($quantity !== '' && bccomp($this->toDecimal($quantity), '0', 3) <= 0) {
                throw new \InvalidArgumentException('La quantité doit être supérieure à zéro (ligne '.($index + 1).').');
            }

            $this->validateExpenseAccount((int) $lineData['expense_account_id'], $companyId, $fiscalYearId);

            if (! empty($lineData['tax_rate_id'])) {
                $this->validateTaxRate((int) $lineData['tax_rate_id'], $companyId);
            }
        }
    }

    private function resolveCurrency(int $companyId): string
    {
        $settings = CompanyAccountingSetting::where('company_id', $companyId)->first();

        return ($settings && $settings->default_currency) ? strtoupper($settings->default_currency) : 'TND';
    }

    /**
     * @param  array<int, array{expense_account_id: int, label: string, quantity?: string|null, unit_price: string, discount_percent: string, tax_rate_id?: int|null}>  $linesData
     */
    private function syncLines(Expense $expense, array $linesData, int $companyId, int $fiscalYearId): void
    {
        $expense->lines()->delete();

        foreach ($linesData as $index => $lineData) {
            $account = $this->validateExpenseAccount((int) $lineData['expense_account_id'], $companyId, $fiscalYearId);

            $taxRate = null;
            if (! empty($lineData['tax_rate_id'])) {
                $taxRate = $this->validateTaxRate((int) $lineData['tax_rate_id'], $companyId);
            }

            $calculated = $this->calculateLine(
                $lineData['quantity'] ?? null,
                $lineData['unit_price'],
                $lineData['discount_percent'],
                $taxRate
            );

            ExpenseLine::create([
                'expense_id' => $expense->id,
                'expense_account_id' => $account->id,
                'label' => trim((string) $lineData['label']),
                'quantity' => $calculated['quantity'],
                'unit_price' => $calculated['unit_price'],
                'discount_percent' => $calculated['discount_percent'],
                'gross_amount' => $calculated['gross_amount'],
                'discount_amount' => $calculated['discount_amount'],
                'line_subtotal' => $calculated['line_subtotal'],
                'tax_rate_id' => $lineData['tax_rate_id'] ?? null,
                'tax_code' => $taxRate ? $taxRate->code : null,
                'tax_rate' => $calculated['tax_rate_value'],
                'tax_amount' => $calculated['tax_amount'],
                'line_total' => $calculated['line_total'],
                'sort_order' => $index + 1,
            ]);
        }
    }

    /**
     * Convert a string to a decimal with 3 decimal places.
     *
     * @return numeric-string
     */
    public function toDecimal(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '0';
        }

        return number_format((float) $normalized, 3, '.', '');
    }
}
