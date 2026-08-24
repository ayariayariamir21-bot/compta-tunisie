<?php

namespace App\Services\Accounting;

use App\Enums\AuditAction;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Services\Security\AuditLogService;

class JournalService
{
    public function __construct(
        private ?AuditLogService $auditLog = null,
    ) {}

    private function audits(): AuditLogService
    {
        return $this->auditLog ?? new AuditLogService;
    }

    /**
     * Create a new journal with full validation.
     *
     * @param  array{code: string, name: string, type: string, is_active?: bool}  $data
     *
     * @throws \InvalidArgumentException
     */
    public function createJournal(
        Company $company,
        FiscalYear $fiscalYear,
        array $data,
    ): Journal {
        if ($fiscalYear->is_closed) {
            throw new \InvalidArgumentException('Impossible de créer un journal sur un exercice clôturé.');
        }

        if ($fiscalYear->company_id !== $company->id) {
            throw new \InvalidArgumentException('L\'exercice n\'appartient pas à cette société.');
        }

        $this->validateCode($data['code'], $company->id, $fiscalYear->id);

        $journal = Journal::create([
            'company_id' => $company->id,
            'fiscal_year_id' => $fiscalYear->id,
            'code' => $data['code'],
            'name' => $data['name'],
            'type' => $data['type'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->audits()->logModelCreated($journal, AuditAction::JournalCreated);

        return $journal;
    }

    /**
     * Update a journal with full validation.
     *
     * @param  array{code: string, name: string, type: string, is_active?: bool}  $data
     *
     * @throws \InvalidArgumentException
     */
    public function updateJournal(Journal $journal, array $data): Journal
    {
        if ($journal->fiscalYear->is_closed) {
            throw new \InvalidArgumentException('Impossible de modifier un journal sur un exercice clôturé.');
        }

        $this->validateCode(
            $data['code'],
            $journal->company_id,
            $journal->fiscal_year_id,
            $journal->id,
        );

        $before = $this->audits()->snapshot($journal, ['code', 'name', 'type', 'is_active']);

        $journal->update([
            'code' => $data['code'],
            'name' => $data['name'],
            'type' => $data['type'],
            'is_active' => $data['is_active'] ?? $journal->is_active,
        ]);

        $journal = $journal->fresh();

        $this->audits()->logModelUpdated(
            $journal,
            AuditAction::JournalUpdated,
            $before,
            $this->audits()->snapshot($journal, ['code', 'name', 'type', 'is_active']),
        );

        return $journal;
    }

    /**
     * Toggle the active state of a journal.
     *
     * @throws \InvalidArgumentException
     */
    public function toggleActive(Journal $journal): Journal
    {
        if ($journal->fiscalYear->is_closed) {
            throw new \InvalidArgumentException('Impossible de modifier un journal sur un exercice clôturé.');
        }

        $journal->update(['is_active' => ! $journal->is_active]);

        return $journal->fresh();
    }

    /**
     * Delete a journal (must not be referenced by future journal entries).
     *
     * @throws \InvalidArgumentException
     */
    public function deleteJournal(Journal $journal): void
    {
        if ($journal->fiscalYear->is_closed) {
            throw new \InvalidArgumentException('Impossible de supprimer un journal sur un exercice clôturé.');
        }

        if ($journal->journalEntries()->exists()) {
            throw new \InvalidArgumentException('Impossible de supprimer un journal utilisé par des écritures.');
        }

        $journal->delete();
    }

    /**
     * Validate that a journal code is unique within the same company and fiscal year.
     *
     * @throws \InvalidArgumentException
     */
    public function validateCode(string $code, int $companyId, int $fiscalYearId, ?int $excludeJournalId = null): void
    {
        $query = Journal::where('company_id', $companyId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('code', $code);

        if ($excludeJournalId) {
            $query->where('id', '!=', $excludeJournalId);
        }

        if ($query->exists()) {
            throw new \InvalidArgumentException('Un journal avec ce code existe déjà pour cet exercice.');
        }
    }
}
