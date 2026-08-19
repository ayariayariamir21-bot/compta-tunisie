<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalYear;

class AccountService
{
    /**
     * Create a new account with full validation.
     *
     * @param  array{code: string, name: string, account_type: string, parent_id?: int|null, is_active?: bool, description?: string}  $data
     *
     * @throws \InvalidArgumentException
     */
    public function createAccount(
        Company $company,
        FiscalYear $fiscalYear,
        array $data,
    ): Account {
        if ($fiscalYear->is_closed) {
            throw new \InvalidArgumentException('Impossible de créer un compte sur un exercice clôturé.');
        }

        if ($fiscalYear->company_id !== $company->id) {
            throw new \InvalidArgumentException('L\'exercice n\'appartient pas à cette société.');
        }

        $this->validateParent($data['parent_id'] ?? null, $company->id, $fiscalYear->id);

        $duplicate = Account::where('company_id', $company->id)
            ->where('fiscal_year_id', $fiscalYear->id)
            ->where('code', $data['code'])
            ->exists();

        if ($duplicate) {
            throw new \InvalidArgumentException('Un compte avec ce code existe déjà pour cet exercice.');
        }

        return Account::create([
            'company_id' => $company->id,
            'fiscal_year_id' => $fiscalYear->id,
            'parent_id' => $data['parent_id'] ?? null,
            'code' => $data['code'],
            'name' => $data['name'],
            'account_type' => $data['account_type'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Update an account with full validation.
     *
     * @param  array{code: string, name: string, account_type: string, parent_id?: int|null, is_active?: bool, description?: string}  $data
     *
     * @throws \InvalidArgumentException
     */
    public function updateAccount(Account $account, array $data): Account
    {
        if ($account->fiscalYear->is_closed) {
            throw new \InvalidArgumentException('Impossible de modifier un compte sur un exercice clôturé.');
        }

        $this->validateParent(
            $data['parent_id'] ?? null,
            $account->company_id,
            $account->fiscal_year_id,
            $account->id,
        );

        $duplicate = Account::where('company_id', $account->company_id)
            ->where('fiscal_year_id', $account->fiscal_year_id)
            ->where('code', $data['code'])
            ->where('id', '!=', $account->id)
            ->exists();

        if ($duplicate) {
            throw new \InvalidArgumentException('Un compte avec ce code existe déjà pour cet exercice.');
        }

        $account->update([
            'parent_id' => $data['parent_id'] ?? null,
            'code' => $data['code'],
            'name' => $data['name'],
            'account_type' => $data['account_type'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? $account->is_active,
        ]);

        return $account->fresh();
    }

    /**
     * Toggle the active state of an account.
     *
     * @throws \InvalidArgumentException
     */
    public function toggleActive(Account $account): Account
    {
        if ($account->fiscalYear->is_closed) {
            throw new \InvalidArgumentException('Impossible de modifier un compte sur un exercice clôturé.');
        }

        $account->update(['is_active' => ! $account->is_active]);

        return $account->fresh();
    }

    /**
     * Delete an account (must have no children).
     *
     * @throws \InvalidArgumentException
     */
    public function deleteAccount(Account $account): void
    {
        if ($account->fiscalYear->is_closed) {
            throw new \InvalidArgumentException('Impossible de supprimer un compte sur un exercice clôturé.');
        }

        if ($account->hasChildren()) {
            throw new \InvalidArgumentException('Impossible de supprimer un compte qui possède des sous-comptes.');
        }

        $account->delete();
    }

    /**
     * Validate that a parent_id is safe (same company/fiscal year, no cycle, no self-reference).
     *
     * @throws \InvalidArgumentException
     */
    public function validateParent(?int $parentId, int $companyId, int $fiscalYearId, ?int $excludeAccountId = null): void
    {
        if ($parentId === null) {
            return;
        }

        if ($excludeAccountId && $parentId === $excludeAccountId) {
            throw new \InvalidArgumentException('Un compte ne peut pas être son propre parent.');
        }

        $parent = Account::where('id', $parentId)
            ->where('company_id', $companyId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->first();

        if (! $parent) {
            throw new \InvalidArgumentException('Le compte parent sélectionné n\'existe pas dans cette société/exercice.');
        }

        if ($excludeAccountId) {
            $parentAccount = Account::find($parentId);

            if ($parentAccount && $parentAccount->isDescendantOf($excludeAccountId)) {
                throw new \InvalidArgumentException('Cette hiérarchie créerait une boucle infinie.');
            }
        }
    }
}
