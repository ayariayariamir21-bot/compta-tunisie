<?php

namespace App\Services\Accounting;

use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class JournalEntryService
{
    /**
     * Create a new draft journal entry with lines.
     *
     * @throws \InvalidArgumentException
     */
    public function createDraft(
        Company $company,
        FiscalYear $fiscalYear,
        AccountingPeriod $period,
        array $data,
        array $linesData,
        int $userId,
    ): JournalEntry {
        $this->validateContext($company, $fiscalYear, $period);
        $journal = $this->validateJournal($data['journal_id'], $company->id, $fiscalYear->id);
        $this->validateEntryDate($data['entry_date'], $period);
        $this->validateLines($linesData, $company->id, $fiscalYear->id);

        $entry = DB::transaction(function () use ($company, $fiscalYear, $period, $journal, $data, $linesData, $userId) {
            $entry = JournalEntry::create([
                'company_id' => $company->id,
                'fiscal_year_id' => $fiscalYear->id,
                'accounting_period_id' => $period->id,
                'journal_id' => $journal->id,
                'entry_number' => $this->generateEntryNumber($company->id, $fiscalYear->id, $journal->id),
                'entry_date' => $data['entry_date'],
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => JournalEntryStatus::DRAFT,
                'created_by' => $userId,
            ]);

            foreach ($linesData as $line) {
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $line['account_id'],
                    'description' => $line['description'] ?? null,
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                ]);
            }

            return $entry;
        });

        return $entry->fresh(['lines.account']);
    }

    /**
     * Update a draft journal entry and its lines.
     *
     * @throws \InvalidArgumentException
     */
    public function updateDraft(JournalEntry $entry, array $data, array $linesData): JournalEntry
    {
        if (! $entry->isDraft()) {
            throw new \InvalidArgumentException('Seules les écritures en brouillon peuvent être modifiées.');
        }

        $this->validateContext($entry->company, $entry->fiscalYear, $entry->accountingPeriod);
        $this->validateJournal($data['journal_id'], $entry->company_id, $entry->fiscal_year_id);
        $this->validateEntryDate($data['entry_date'], $entry->accountingPeriod);
        $this->validateLines($linesData, $entry->company_id, $entry->fiscal_year_id);

        DB::transaction(function () use ($entry, $data, $linesData) {
            $entry->update([
                'journal_id' => $data['journal_id'],
                'entry_date' => $data['entry_date'],
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
            ]);

            $entry->lines()->delete();

            foreach ($linesData as $line) {
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $line['account_id'],
                    'description' => $line['description'] ?? null,
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                ]);
            }
        });

        return $entry->fresh(['lines.account']);
    }

    /**
     * Post a draft journal entry (validate balance, mark as posted).
     *
     * @throws \InvalidArgumentException
     */
    public function post(JournalEntry $entry): JournalEntry
    {
        if (! $entry->isDraft()) {
            throw new \InvalidArgumentException('Seules les écritures en brouillon peuvent être comptabilisées.');
        }

        $this->validateContext($entry->company, $entry->fiscalYear, $entry->accountingPeriod);
        $this->validateJournal($entry->journal_id, $entry->company_id, $entry->fiscal_year_id);

        $lines = $entry->lines()->get();

        if ($lines->count() < 2) {
            throw new \InvalidArgumentException('Une écriture doit contenir au moins 2 lignes pour être comptabilisée.');
        }

        $this->validateLineRules($lines);
        $this->validateBalance($lines);

        DB::transaction(function () use ($entry) {
            $entry->update([
                'status' => JournalEntryStatus::POSTED,
                'posted_at' => now(),
            ]);
        });

        return $entry->fresh(['lines.account', 'journal', 'creator']);
    }

    /**
     * Cancel a draft journal entry.
     *
     * @throws \InvalidArgumentException
     */
    public function cancel(JournalEntry $entry): JournalEntry
    {
        if (! $entry->isDraft()) {
            throw new \InvalidArgumentException('Seules les écritures en brouillon peuvent être annulées.');
        }

        DB::transaction(function () use ($entry) {
            $entry->update([
                'status' => JournalEntryStatus::CANCELLED,
            ]);
        });

        return $entry->fresh(['lines.account', 'journal', 'creator']);
    }

    /**
     * Delete a draft journal entry and its lines.
     *
     * @throws \InvalidArgumentException
     */
    public function deleteDraft(JournalEntry $entry): void
    {
        if (! $entry->isDraft()) {
            throw new \InvalidArgumentException('Seules les écritures en brouillon peuvent être supprimées.');
        }

        DB::transaction(function () use ($entry) {
            $entry->lines()->delete();
            $entry->delete();
        });
    }

    /**
     * Validate company/fiscal year/period context.
     *
     * @throws \InvalidArgumentException
     */
    public function validateContext(Company $company, FiscalYear $fiscalYear, AccountingPeriod $period): void
    {
        if ($fiscalYear->company_id !== $company->id) {
            throw new \InvalidArgumentException('L\'exercice n\'appartient pas à cette société.');
        }

        if ($period->fiscal_year_id !== $fiscalYear->id) {
            throw new \InvalidArgumentException('La période n\'appartient pas à cet exercice.');
        }

        if ($fiscalYear->is_closed) {
            throw new \InvalidArgumentException('Impossible de modifier une écriture sur un exercice clôturé.');
        }

        if (! $period->is_open) {
            throw new \InvalidArgumentException('Impossible de modifier une écriture sur une période clôturée.');
        }
    }

    /**
     * Validate that a journal belongs to the given company and fiscal year and is active.
     *
     * @throws \InvalidArgumentException
     */
    public function validateJournal(int $journalId, int $companyId, int $fiscalYearId): Journal
    {
        $journal = Journal::where('id', $journalId)
            ->where('company_id', $companyId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->first();

        if (! $journal) {
            throw new \InvalidArgumentException('Le journal sélectionné n\'existe pas dans cette société/exercice.');
        }

        if (! $journal->is_active) {
            throw new \InvalidArgumentException('Le journal sélectionné est inactif.');
        }

        return $journal;
    }

    /**
     * Validate entry date falls within the accounting period.
     *
     * @throws \InvalidArgumentException
     */
    public function validateEntryDate(string $entryDate, AccountingPeriod $period): void
    {
        $date = Carbon::parse($entryDate);

        if ($date->lt($period->start_date) || $date->gt($period->end_date)) {
            throw new \InvalidArgumentException(
                "La date de l'écriture doit se situer dans la période « {$period->name} » ({$period->start_date->format('d/m/Y')} — {$period->end_date->format('d/m/Y')})."
            );
        }
    }

    /**
     * Validate line data structure and account ownership.
     *
     * @throws \InvalidArgumentException
     */
    public function validateLines(array $linesData, int $companyId, int $fiscalYearId): void
    {
        if (empty($linesData)) {
            throw new \InvalidArgumentException('L\'écriture doit contenir au moins une ligne.');
        }

        foreach ($linesData as $index => $line) {
            $num = $index + 1;

            if (empty($line['account_id'])) {
                throw new \InvalidArgumentException("La ligne {$num} doit contenir un compte.");
            }

            $account = Account::where('id', $line['account_id'])
                ->where('company_id', $companyId)
                ->where('fiscal_year_id', $fiscalYearId)
                ->first();

            if (! $account) {
                throw new \InvalidArgumentException("Le compte de la ligne {$num} n'appartient pas à cette société/exercice.");
            }

            if (! $account->is_active) {
                throw new \InvalidArgumentException("Le compte de la ligne {$num} est inactif.");
            }

            $debit = $line['debit'] ?? 0;
            $credit = $line['credit'] ?? 0;

            if (bccomp((string) $debit, '0', 3) < 0 || bccomp((string) $credit, '0', 3) < 0) {
                throw new \InvalidArgumentException("Les montants de la ligne {$num} doivent être positifs.");
            }

            if (bccomp((string) $debit, '0', 3) > 0 && bccomp((string) $credit, '0', 3) > 0) {
                throw new \InvalidArgumentException("La ligne {$num} ne peut pas avoir à la fois un débit et un crédit.");
            }

            if (bccomp((string) $debit, '0', 3) === 0 && bccomp((string) $credit, '0', 3) === 0) {
                throw new \InvalidArgumentException("La ligne {$num} doit avoir un débit ou un crédit supérieur à zéro.");
            }
        }
    }

    /**
     * Validate debit/credit rules on posted entry lines.
     *
     * @throws \InvalidArgumentException
     */
    public function validateLineRules($lines): void
    {
        foreach ($lines as $line) {
            $debit = $line->debit;
            $credit = $line->credit;

            if (bccomp((string) $debit, '0', 3) < 0 || bccomp((string) $credit, '0', 3) < 0) {
                throw new \InvalidArgumentException('Les montants doivent être positifs.');
            }

            if (bccomp((string) $debit, '0', 3) > 0 && bccomp((string) $credit, '0', 3) > 0) {
                throw new \InvalidArgumentException('Une ligne ne peut pas avoir à la fois un débit et un crédit.');
            }

            if (bccomp((string) $debit, '0', 3) === 0 && bccomp((string) $credit, '0', 3) === 0) {
                throw new \InvalidArgumentException('Chaque ligne doit avoir un débit ou un crédit supérieur à zéro.');
            }
        }
    }

    /**
     * Validate that total debit equals total credit.
     *
     * @throws \InvalidArgumentException
     */
    public function validateBalance($lines): void
    {
        $totalDebit = '0';
        $totalCredit = '0';

        foreach ($lines as $line) {
            $totalDebit = bcadd($totalDebit, (string) $line->debit, 3);
            $totalCredit = bcadd($totalCredit, (string) $line->credit, 3);
        }

        if (bccomp($totalDebit, $totalCredit, 3) !== 0) {
            throw new \InvalidArgumentException('Le total débit doit être égal au total crédit.');
        }
    }

    /**
     * Generate a unique entry number for the given company, fiscal year, and journal.
     *
     * Format: {JOURNAL_CODE}-{YEAR}-{SEQUENCE}
     * Uses PostgreSQL row-level locking to prevent concurrent duplicates.
     */
    public function generateEntryNumber(int $companyId, int $fiscalYearId, int $journalId): string
    {
        $journal = Journal::find($journalId);
        $fy = FiscalYear::find($fiscalYearId);

        $prefix = $journal->code.'-'.$fy->code.'-';

        $lastEntry = DB::table('journal_entries')
            ->where('company_id', $companyId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('journal_id', $journalId)
            ->orderByDesc('entry_number')
            ->lockForUpdate()
            ->first();

        if ($lastEntry) {
            $lastSequence = (int) substr($lastEntry->entry_number, strlen($prefix));
            $nextSequence = $lastSequence + 1;
        } else {
            $nextSequence = 1;
        }

        return $prefix.str_pad((string) $nextSequence, 6, '0', STR_PAD_LEFT);
    }
}
