<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Customer;

class CustomerService
{
    /**
     * Create a new customer.
     *
     * @throws \InvalidArgumentException
     */
    public function createCustomer(array $data): Customer
    {
        $companyId = $data['company_id'];
        $this->validateCode($data['code'], $companyId);

        if (isset($data['account_id']) && $data['account_id'] !== null) {
            $this->validateAccount($data['account_id'], $companyId, $data['fiscal_year_id']);
        }

        unset($data['fiscal_year_id']);

        return Customer::create($data);
    }

    /**
     * Update a customer.
     *
     * @throws \InvalidArgumentException
     */
    public function updateCustomer(Customer $customer, array $data): Customer
    {
        $this->validateCode($data['code'], $customer->company_id, $customer->id);

        if (isset($data['account_id']) && $data['account_id'] !== null) {
            $this->validateAccount($data['account_id'], $customer->company_id, $data['fiscal_year_id']);
        }

        unset($data['fiscal_year_id']);

        $customer->update($data);

        return $customer->fresh();
    }

    /**
     * Activate a customer.
     */
    public function activate(Customer $customer): Customer
    {
        $customer->update(['is_active' => true]);

        return $customer->fresh();
    }

    /**
     * Deactivate a customer.
     */
    public function deactivate(Customer $customer): Customer
    {
        $customer->update(['is_active' => false]);

        return $customer->fresh();
    }

    /**
     * Delete a customer.
     *
     * Allows deletion only when no transactional references exist.
     * Future invoice/payment references must block deletion.
     *
     * @throws \InvalidArgumentException
     */
    public function delete(Customer $customer): void
    {
        if ($this->hasTransactionalReferences($customer)) {
            throw new \InvalidArgumentException(
                'Impossible de supprimer ce client. Des références transactionnelles existent. Veuillez plutôt le désactiver.'
            );
        }

        $customer->delete();
    }

    /**
     * Generate a unique customer code for the given company.
     *
     * Strategy: Extract the maximum numeric suffix from existing codes
     * matching the CL prefix, increment, and retry if a race condition
     * causes a collision.
     *
     * @throws \RuntimeException
     */
    public function generateCode(int $companyId): string
    {
        $prefix = 'CL';
        $maxTries = 10;

        $maxNumber = Customer::where('company_id', $companyId)
            ->where('code', 'like', $prefix.'%')
            ->pluck('code')
            ->map(fn (string $code) => (int) substr($code, strlen($prefix)))
            ->filter(fn (int $num) => $num > 0)
            ->max() ?? 0;

        for ($attempt = 0; $attempt < $maxTries; $attempt++) {
            $maxNumber++;
            $candidate = $prefix.str_pad((string) $maxNumber, 3, '0', STR_PAD_LEFT);

            if (! Customer::where('company_id', $companyId)->where('code', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Impossible de générer un code client unique après '.$maxTries.' tentatives.');
    }

    /**
     * Validate that an account belongs to the current company and fiscal year,
     * and is active.
     *
     * @throws \InvalidArgumentException
     */
    public function validateAccount(int $accountId, int $companyId, int $fiscalYearId): void
    {
        $account = Account::where('id', $accountId)
            ->where('company_id', $companyId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->first();

        if (! $account) {
            throw new \InvalidArgumentException(
                'Le compte sélectionné n\'appartient pas à cette société ou cet exercice.'
            );
        }

        if (! $account->is_active) {
            throw new \InvalidArgumentException(
                'Le compte sélectionné est inactif. Veuillez choisir un compte actif.'
            );
        }
    }

    /**
     * Check if a customer has any transactional references.
     *
     * Placeholder for future invoice/payment checks.
     */
    private function hasTransactionalReferences(Customer $customer): bool
    {
        // Future: check invoices, payments, credit notes, etc.
        return false;
    }

    /**
     * Validate that a code is unique within a company.
     *
     * @throws \InvalidArgumentException
     */
    private function validateCode(string $code, int $companyId, ?int $excludeId = null): void
    {
        $query = Customer::where('company_id', $companyId)
            ->where('code', $code);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw new \InvalidArgumentException(
                'Un client avec ce code existe déjà pour cette société.'
            );
        }
    }
}
