<?php

namespace App\Services\Accounting;

use App\Enums\ExpenseStatus;
use App\Enums\JournalEntryStatus;
use App\Enums\JournalType;
use App\Models\Account;
use App\Models\CompanyAccountingSetting;
use App\Models\Expense;
use App\Models\ExpenseLine;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Services\SupplierPaymentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ExpensePostingService
{
    public function __construct(
        private JournalEntryService $journalEntryService,
        private SupplierPaymentService $supplierPaymentService,
    ) {}

    public function post(Expense $expense, int $userId): Expense
    {
        return DB::transaction(function () use ($expense, $userId) {
            $expense->load(['lines.expenseAccount', 'lines.taxRate', 'supplier', 'paymentMethod', 'fiscalYear', 'accountingPeriod', 'journal']);

            $this->validatePreconditions($expense);

            /** @var array<int, int> $expenseAccountIds */
            $expenseAccountIds = [];

            foreach ($expense->lines as $line) {
                $expenseAccountIds[$line->id] = $line->expense_account_id;
            }

            foreach (array_unique($expenseAccountIds) as $accountId) {
                $this->validateExpenseAccountId($accountId, $expense);
            }

            [$creditAccountId, $creditDescription] = $this->resolveCreditSide($expense);

            $journalEntry = $this->createJournalEntry($expense, $userId);
            $this->createJournalEntryLines($journalEntry, $expense, $creditAccountId, $creditDescription);

            if (! $journalEntry->fresh()->isBalanced()) {
                throw new \RuntimeException('L\'écriture comptable n\'est pas équilibrée.');
            }

            $journalEntry->update([
                'status' => JournalEntryStatus::POSTED,
                'posted_at' => now(),
            ]);

            $expense->update([
                'status' => ExpenseStatus::POSTED,
                'posted_at' => now(),
                'journal_entry_id' => $journalEntry->id,
            ]);

            return $expense->fresh(['lines.expenseAccount', 'lines.taxRate', 'supplier', 'paymentMethod', 'journalEntry', 'journal', 'fiscalYear', 'accountingPeriod']);
        });
    }

    private function validatePreconditions(Expense $expense): void
    {
        if ($expense->status !== ExpenseStatus::DRAFT) {
            throw new \InvalidArgumentException('Seule une dépense en brouillon peut être comptabilisée.');
        }

        if ($expense->lines()->count() === 0) {
            throw new \InvalidArgumentException('Une dépense doit contenir au moins une ligne.');
        }

        if ($expense->fiscalYear->is_closed) {
            throw new \InvalidArgumentException('L\'exercice comptable est clôturé. Impossible de comptabiliser.');
        }

        if ($expense->accountingPeriod->is_closed || ! $expense->accountingPeriod->is_open) {
            throw new \InvalidArgumentException('La période comptable est clôturée. Impossible de comptabiliser.');
        }

        if ($expense->journal->type !== JournalType::OPERATIONS_DIVERSES) {
            throw new \InvalidArgumentException('Le journal sélectionné n\'est pas un journal d\'opérations diverses.');
        }

        $periodStart = Carbon::parse($expense->accountingPeriod->start_date);
        $periodEnd = Carbon::parse($expense->accountingPeriod->end_date);
        $expenseDate = $expense->expense_date;

        if ($expenseDate->lt($periodStart) || $expenseDate->gt($periodEnd)) {
            throw new \InvalidArgumentException('La date de la dépense doit être comprise dans la période comptable.');
        }
    }

    /**
     * Resolve the credit side of the entry.
     *
     * Immediate payment mode when a payment method is set (credit bank/cash),
     * supplier payable mode otherwise (credit the supplier account).
     *
     * @return array{0: int, 1: string} [account id, credit line description]
     */
    private function resolveCreditSide(Expense $expense): array
    {
        if ($expense->payment_method_id !== null && $expense->paymentMethod !== null) {
            return $this->resolveImmediatePaymentCredit($expense);
        }

        if ($expense->supplier_id !== null && $expense->supplier !== null) {
            return $this->resolveSupplierPayableCredit($expense);
        }

        throw new \InvalidArgumentException('Aucun mode de règlement ni fournisseur n\'est associé à cette dépense. Impossible de déterminer le compte de contrepartie. Veuillez sélectionner un mode de paiement ou un fournisseur.');
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function resolveImmediatePaymentCredit(Expense $expense): array
    {
        $method = $expense->paymentMethod;

        $destination = $this->supplierPaymentService->resolveDestinationAccount($method, $expense->company_id, $expense->fiscal_year_id);

        if (! $destination instanceof Account) {
            throw new \RuntimeException("Aucun compte de destination n'est configuré pour le mode de règlement « {$method->name} ». Veuillez configurer un compte banque ou caisse par défaut dans les paramètres comptables.");
        }

        $accountId = Account::where('id', $destination->id)
            ->where('company_id', $expense->company_id)
            ->where('fiscal_year_id', $expense->fiscal_year_id)
            ->where('is_active', true)
            ->value('id');

        if ($accountId === null) {
            throw new \RuntimeException("Le compte de destination configuré pour le mode de règlement « {$method->name} » est introuvable, inactif ou n'appartient pas à l'exercice courant.");
        }

        return [(int) $accountId, "Dépense {$expense->expense_number} — Règlement: {$method->name}"];
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function resolveSupplierPayableCredit(Expense $expense): array
    {
        $supplier = $expense->supplier;

        $accountId = $supplier->account_id;

        if (! $accountId) {
            $settings = CompanyAccountingSetting::where('company_id', $expense->company_id)->first();
            $accountId = $settings?->default_supplier_account_id;
        }

        if (! $accountId) {
            throw new \RuntimeException("Aucun compte fournisseur configuré pour « {$supplier->name} ». Veuillez assigner un compte de tiers au fournisseur ou configurer un compte fournisseur par défaut dans les paramètres comptables.");
        }

        $accountId = Account::where('id', $accountId)
            ->where('company_id', $expense->company_id)
            ->where('fiscal_year_id', $expense->fiscal_year_id)
            ->where('is_active', true)
            ->value('id');

        if ($accountId === null) {
            throw new \RuntimeException("Le compte fournisseur configuré pour « {$supplier->name} » est introuvable, inactif ou n'appartient pas à l'exercice courant.");
        }

        return [(int) $accountId, "Dépense {$expense->expense_number} — Fournisseur: {$expense->supplier->name}"];
    }

    private function validateExpenseAccountId(int $accountId, Expense $expense): void
    {
        Account::where('id', $accountId)
            ->where('company_id', $expense->company_id)
            ->where('fiscal_year_id', $expense->fiscal_year_id)
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function createJournalEntry(Expense $expense, int $userId): JournalEntry
    {
        return JournalEntry::create([
            'company_id' => $expense->company_id,
            'fiscal_year_id' => $expense->fiscal_year_id,
            'accounting_period_id' => $expense->accounting_period_id,
            'journal_id' => $expense->journal_id,
            'entry_number' => $this->journalEntryService->generateEntryNumber(
                $expense->company_id,
                $expense->fiscal_year_id,
                $expense->journal_id
            ),
            'entry_date' => $expense->expense_date,
            'reference' => $expense->expense_number,
            'description' => $this->buildEntryDescription($expense),
            'status' => JournalEntryStatus::DRAFT,
            'created_by' => $userId,
        ]);
    }

    private function buildEntryDescription(Expense $expense): string
    {
        if ($expense->supplier !== null) {
            return "Dépense {$expense->expense_number} — {$expense->supplier->name}";
        }

        if ($expense->description !== null && trim($expense->description) !== '') {
            return "Dépense {$expense->expense_number} — {$expense->description}";
        }

        return "Dépense {$expense->expense_number}";
    }

    private function createJournalEntryLines(JournalEntry $journalEntry, Expense $expense, int $creditAccountId, string $creditDescription): void
    {
        /** @var array<int, numeric-string> $expenseAccountTotals */
        $expenseAccountTotals = [];
        /** @var array<int, numeric-string> $taxAccountTotals */
        $taxAccountTotals = [];

        foreach ($expense->lines as $line) {
            $accountId = $line->expense_account_id;
            if (! isset($expenseAccountTotals[$accountId])) {
                $expenseAccountTotals[$accountId] = '0';
            }
            $expenseAccountTotals[$accountId] = bcadd($expenseAccountTotals[$accountId], (string) $line->line_subtotal, 3);

            if (bccomp((string) $line->tax_amount, '0', 3) > 0) {
                $taxAccountId = $this->resolveTaxAccountId($line);
                if (! isset($taxAccountTotals[$taxAccountId])) {
                    $taxAccountTotals[$taxAccountId] = '0';
                }
                $taxAccountTotals[$taxAccountId] = bcadd($taxAccountTotals[$taxAccountId], (string) $line->tax_amount, 3);
            }
        }

        foreach ($expenseAccountTotals as $accountId => $amount) {
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $accountId,
                'description' => "Charges — Dépense {$expense->expense_number}",
                'debit' => $amount,
                'credit' => '0.000',
            ]);
        }

        foreach ($taxAccountTotals as $accountId => $amount) {
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $accountId,
                'description' => "TVA récupérable — Dépense {$expense->expense_number}",
                'debit' => $amount,
                'credit' => '0.000',
            ]);
        }

        JournalEntryLine::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $creditAccountId,
            'description' => $creditDescription,
            'debit' => '0.000',
            'credit' => $expense->total,
        ]);
    }

    private function resolveTaxAccountId(ExpenseLine $line): int
    {
        if ($line->taxRate && $line->taxRate->purchase_tax_account_id) {
            return $line->taxRate->purchase_tax_account_id;
        }

        throw new \RuntimeException("Aucun compte de TVA récupérable n'est configuré pour le taux « {$line->tax_code} ». Veuillez configurer le champ « Compte d'achat (TVA) » sur ce taux de taxe.");
    }
}
