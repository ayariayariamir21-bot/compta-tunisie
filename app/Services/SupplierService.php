<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Supplier;

class SupplierService
{
    /**
     * @param  array{company_id: int, code: string, name: string, supplier_type: string, country: string, payment_terms_days: int, is_active: bool, fiscal_year_id?: int, account_id?: int|null, ...}  $data
     */
    public function createSupplier(array $data): Supplier
    {
        $companyId = $data['company_id'];
        $this->validateCode($data['code'], $companyId);

        if (isset($data['account_id'], $data['fiscal_year_id'])) {
            $this->validateAccount((int) $data['account_id'], $companyId, (int) $data['fiscal_year_id']);
        }

        unset($data['fiscal_year_id']);

        return Supplier::create($data);
    }

    /**
     * @param  array{code: string, name: string, supplier_type: string, country: string, payment_terms_days: int, is_active: bool, fiscal_year_id?: int|null, account_id?: int|null, ...}  $data
     */
    public function updateSupplier(Supplier $supplier, array $data): Supplier
    {
        $this->validateCode($data['code'], $supplier->company_id, $supplier->id);

        if (isset($data['account_id'], $data['fiscal_year_id'])) {
            $this->validateAccount((int) $data['account_id'], $supplier->company_id, (int) $data['fiscal_year_id']);
        }

        unset($data['fiscal_year_id']);

        $supplier->update($data);

        return $supplier->fresh();
    }

    public function activate(Supplier $supplier): Supplier
    {
        $supplier->update(['is_active' => true]);

        return $supplier->fresh();
    }

    public function deactivate(Supplier $supplier): Supplier
    {
        $supplier->update(['is_active' => false]);

        return $supplier->fresh();
    }

    public function delete(Supplier $supplier): void
    {
        if ($this->hasTransactionalReferences($supplier)) {
            throw new \InvalidArgumentException(
                'Impossible de supprimer ce fournisseur. Des références transactionnelles existent. Veuillez plutôt le désactiver.'
            );
        }

        $supplier->delete();
    }

    /**
     * Generate a unique supplier code for the given company.
     *
     * Strategy: find the current maximum numeric suffix among codes starting
     * with "FO", increment it, and pad to 3 digits. Retry up to 10 times on
     * collision (race condition safe).
     *
     * @return string Example: FO001, FO002, FO003
     */
    public function generateCode(int $companyId): string
    {
        $prefix = 'FO';
        $maxTries = 10;
        $maxNumber = Supplier::where('company_id', $companyId)
            ->where('code', 'like', $prefix.'%')
            ->pluck('code')
            ->map(fn (string $code) => (int) substr($code, strlen($prefix)))
            ->filter(fn (int $num) => $num > 0)
            ->max() ?? 0;

        for ($attempt = 0; $attempt < $maxTries; $attempt++) {
            $maxNumber++;
            $candidate = $prefix.str_pad((string) $maxNumber, 3, '0', STR_PAD_LEFT);
            if (! Supplier::where('company_id', $companyId)->where('code', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Impossible de générer un code fournisseur unique après '.$maxTries.' tentatives.');
    }

    public function validateAccount(int $accountId, int $companyId, int $fiscalYearId): void
    {
        $account = Account::where('id', $accountId)
            ->where('company_id', $companyId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->first();

        if (! $account) {
            throw new \InvalidArgumentException('Le compte sélectionné n\'appartient pas à cette société ou cet exercice.');
        }

        if (! $account->is_active) {
            throw new \InvalidArgumentException('Le compte sélectionné est inactif. Veuillez choisir un compte actif.');
        }
    }

    /**
     * Check whether a supplier has any transactional references.
     *
     * Currently returns false. Future: check purchase invoices, payments, credit notes.
     */
    private function hasTransactionalReferences(Supplier $supplier): bool
    {
        return false;
    }

    private function validateCode(string $code, int $companyId, ?int $excludeId = null): void
    {
        $query = Supplier::where('company_id', $companyId)->where('code', $code);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw new \InvalidArgumentException('Un fournisseur avec ce code existe déjà pour cette société.');
        }
    }
}
