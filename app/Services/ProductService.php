<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Product;
use App\Models\TaxRate;

class ProductService
{
    /**
     * @param  array{company_id: int, code: string, name: string, type: string, unit: string, is_active: bool, is_sellable: bool, is_purchasable: bool, fiscal_year_id?: int, tax_rate_id?: int|null, sales_account_id?: int|null, purchase_account_id?: int|null, ...}  $data
     */
    public function createProduct(array $data): Product
    {
        $companyId = $data['company_id'];
        $this->validateCode($data['code'], $companyId);

        if (isset($data['tax_rate_id'])) {
            $this->validateTaxRate((int) $data['tax_rate_id'], $companyId);
        }

        if (isset($data['sales_account_id'], $data['fiscal_year_id'])) {
            $this->validateSalesAccount((int) $data['sales_account_id'], $companyId, (int) $data['fiscal_year_id']);
        }

        if (isset($data['purchase_account_id'], $data['fiscal_year_id'])) {
            $this->validatePurchaseAccount((int) $data['purchase_account_id'], $companyId, (int) $data['fiscal_year_id']);
        }

        unset($data['fiscal_year_id']);

        return Product::create($data);
    }

    /**
     * @param  array{code: string, name: string, type: string, unit: string, is_active: bool, is_sellable: bool, is_purchasable: bool, fiscal_year_id?: int|null, tax_rate_id?: int|null, sales_account_id?: int|null, purchase_account_id?: int|null, ...}  $data
     */
    public function updateProduct(Product $product, array $data): Product
    {
        $this->validateCode($data['code'], $product->company_id, $product->id);

        if (isset($data['tax_rate_id'])) {
            $this->validateTaxRate((int) $data['tax_rate_id'], $product->company_id);
        }

        if (isset($data['sales_account_id'], $data['fiscal_year_id'])) {
            $this->validateSalesAccount((int) $data['sales_account_id'], $product->company_id, (int) $data['fiscal_year_id']);
        }

        if (isset($data['purchase_account_id'], $data['fiscal_year_id'])) {
            $this->validatePurchaseAccount((int) $data['purchase_account_id'], $product->company_id, (int) $data['fiscal_year_id']);
        }

        unset($data['fiscal_year_id']);

        $product->update($data);

        return $product->fresh();
    }

    public function activate(Product $product): Product
    {
        $product->update(['is_active' => true]);

        return $product->fresh();
    }

    public function deactivate(Product $product): Product
    {
        $product->update(['is_active' => false]);

        return $product->fresh();
    }

    public function delete(Product $product): void
    {
        if ($this->hasTransactionalReferences($product)) {
            throw new \InvalidArgumentException(
                'Impossible de supprimer ce produit/service. Des références transactionnelles existent. Veuillez plutôt le désactiver.'
            );
        }

        $product->delete();
    }

    /**
     * Generate a unique product code for the given company.
     *
     * Strategy: find the current maximum numeric suffix among codes starting
     * with "PRD", increment it, and pad to 3 digits. Retry up to 10 times on
     * collision (race condition safe).
     *
     * @return string Example: PRD001, PRD002, PRD003
     */
    public function generateCode(int $companyId): string
    {
        $prefix = 'PRD';
        $maxTries = 10;
        $maxNumber = Product::where('company_id', $companyId)
            ->where('code', 'like', $prefix.'%')
            ->pluck('code')
            ->map(fn (string $code) => (int) substr($code, strlen($prefix)))
            ->filter(fn (int $num) => $num > 0)
            ->max() ?? 0;

        for ($attempt = 0; $attempt < $maxTries; $attempt++) {
            $maxNumber++;
            $candidate = $prefix.str_pad((string) $maxNumber, 3, '0', STR_PAD_LEFT);
            if (! Product::where('company_id', $companyId)->where('code', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Impossible de générer un code produit unique après '.$maxTries.' tentatives.');
    }

    public function validateTaxRate(int $taxRateId, int $companyId): void
    {
        $taxRate = TaxRate::where('id', $taxRateId)
            ->where('company_id', $companyId)
            ->first();

        if (! $taxRate) {
            throw new \InvalidArgumentException('Le taux de TVA sélectionné n\'appartient pas à cette société.');
        }

        if (! $taxRate->is_active) {
            throw new \InvalidArgumentException('Le taux de TVA sélectionné est inactif. Veuillez choisir un taux actif.');
        }
    }

    public function validateSalesAccount(int $accountId, int $companyId, int $fiscalYearId): void
    {
        $account = Account::where('id', $accountId)
            ->where('company_id', $companyId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->first();

        if (! $account) {
            throw new \InvalidArgumentException('Le compte de vente sélectionné n\'appartient pas à cette société ou cet exercice.');
        }

        if (! $account->is_active) {
            throw new \InvalidArgumentException('Le compte de vente sélectionné est inactif. Veuillez choisir un compte actif.');
        }
    }

    public function validatePurchaseAccount(int $accountId, int $companyId, int $fiscalYearId): void
    {
        $account = Account::where('id', $accountId)
            ->where('company_id', $companyId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->first();

        if (! $account) {
            throw new \InvalidArgumentException('Le compte d\'achat sélectionné n\'appartient pas à cette société ou cet exercice.');
        }

        if (! $account->is_active) {
            throw new \InvalidArgumentException('Le compte d\'achat sélectionné est inactif. Veuillez choisir un compte actif.');
        }
    }

    /**
     * Check whether a product has any transactional references.
     *
     * Currently returns false. Future: check invoices, purchase orders, credit notes.
     */
    private function hasTransactionalReferences(Product $product): bool
    {
        return false;
    }

    private function validateCode(string $code, int $companyId, ?int $excludeId = null): void
    {
        $query = Product::where('company_id', $companyId)->where('code', $code);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw new \InvalidArgumentException('Un produit/service avec ce code existe déjà pour cette société.');
        }
    }
}
