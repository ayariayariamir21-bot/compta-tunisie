<?php

namespace App\Services\Accounting;

use App\Enums\TaxType;
use App\Models\Company;
use App\Models\TaxRate;
use Illuminate\Support\Facades\DB;

class TaxRateService
{
    /**
     * Create a new tax rate.
     *
     * @throws \InvalidArgumentException
     */
    public function create(Company $company, array $data): TaxRate
    {
        $this->validateCode($data['code'], $company->id);
        $this->validateRate($data['rate']);

        if (! TaxType::tryFrom($data['type'])) {
            throw new \InvalidArgumentException('Le type de taxe sélectionné n\'est pas valide.');
        }

        return DB::transaction(function () use ($company, $data) {
            $isDefault = $data['is_default'] ?? false;

            if ($isDefault) {
                $this->clearDefaults($company->id);
            }

            return TaxRate::create([
                'company_id' => $company->id,
                'code' => strtoupper($data['code']),
                'name' => $data['name'],
                'rate' => $data['rate'],
                'type' => $data['type'],
                'is_active' => $data['is_active'] ?? true,
                'is_default' => $isDefault,
                'sort_order' => $data['sort_order'] ?? 0,
                'description' => $data['description'] ?? null,
            ]);
        });
    }

    /**
     * Update a tax rate.
     *
     * @throws \InvalidArgumentException
     */
    public function update(TaxRate $taxRate, array $data): TaxRate
    {
        $this->validateCode($data['code'], $taxRate->company_id, $taxRate->id);
        $this->validateRate($data['rate']);

        if (! TaxType::tryFrom($data['type'])) {
            throw new \InvalidArgumentException('Le type de taxe sélectionné n\'est pas valide.');
        }

        $isDefault = $data['is_default'] ?? false;
        $isActive = $data['is_active'] ?? $taxRate->is_active;

        if ($taxRate->is_default && ! $isDefault && $isActive) {
            throw new \InvalidArgumentException(
                'Impossible de retirer le statut par défaut. Veuillez d\'abord définir un autre taux de taxe par défaut.'
            );
        }

        if ($taxRate->is_default && ! $isActive) {
            throw new \InvalidArgumentException(
                'Impossible de désactiver le taux de taxe par défaut. Veuillez d\'abord définir un autre taux de taxe par défaut.'
            );
        }

        return DB::transaction(function () use ($taxRate, $data, $isDefault) {
            if ($isDefault && ! $taxRate->is_default) {
                $this->clearDefaults($taxRate->company_id);
            }

            $taxRate->update([
                'code' => strtoupper($data['code']),
                'name' => $data['name'],
                'rate' => $data['rate'],
                'type' => $data['type'],
                'is_active' => $data['is_active'] ?? $taxRate->is_active,
                'is_default' => $isDefault,
                'sort_order' => $data['sort_order'] ?? $taxRate->sort_order,
                'description' => $data['description'] ?? null,
            ]);

            return $taxRate->fresh();
        });
    }

    /**
     * Activate a tax rate.
     */
    public function activate(TaxRate $taxRate): TaxRate
    {
        $taxRate->update(['is_active' => true]);

        return $taxRate->fresh();
    }

    /**
     * Deactivate a tax rate.
     *
     * @throws \InvalidArgumentException
     */
    public function deactivate(TaxRate $taxRate): TaxRate
    {
        if ($taxRate->is_default) {
            throw new \InvalidArgumentException(
                'Impossible de désactiver le taux de taxe par défaut. Veuillez d\'abord définir un autre taux de taxe par défaut.'
            );
        }

        $taxRate->update(['is_active' => false]);

        return $taxRate->fresh();
    }

    /**
     * Set a tax rate as the default.
     */
    public function setDefault(TaxRate $taxRate): TaxRate
    {
        return DB::transaction(function () use ($taxRate) {
            $this->clearDefaults($taxRate->company_id);

            $taxRate->update(['is_default' => true]);

            return $taxRate->fresh();
        });
    }

    /**
     * Delete a tax rate.
     *
     * @throws \InvalidArgumentException
     */
    public function delete(TaxRate $taxRate): void
    {
        if ($taxRate->is_default) {
            throw new \InvalidArgumentException(
                'Impossible de supprimer le taux de taxe par défaut. Veuillez d\'abord définir un autre taux de taxe par défaut.'
            );
        }

        $taxRate->delete();
    }

    /**
     * Initialize default tax rates for a company (idempotent).
     *
     * NOTE: These are starter configuration values only.
     * They are NOT claimed as legally authoritative.
     * Current Tunisian tax laws must be verified before production use.
     */
    public function initializeDefaults(Company $company): int
    {
        $defaults = [
            ['code' => 'TVA19', 'name' => 'TVA 19%', 'rate' => '19.000', 'type' => TaxType::VAT, 'sort_order' => 1],
            ['code' => 'TVA13', 'name' => 'TVA 13%', 'rate' => '13.000', 'type' => TaxType::VAT, 'sort_order' => 2],
            ['code' => 'TVA07', 'name' => 'TVA 7%', 'rate' => '7.000', 'type' => TaxType::VAT, 'sort_order' => 3, 'is_default' => true],
            ['code' => 'EXO', 'name' => 'Exonéré', 'rate' => '0.000', 'type' => TaxType::EXEMPT, 'sort_order' => 4],
            ['code' => 'TVA0', 'name' => 'Taux zéro', 'rate' => '0.000', 'type' => TaxType::ZERO_RATED, 'sort_order' => 5],
        ];

        $existingCodes = TaxRate::where('company_id', $company->id)
            ->pluck('code')
            ->toArray();

        $count = 0;

        foreach ($defaults as $default) {
            if (! in_array($default['code'], $existingCodes)) {
                TaxRate::create([
                    'company_id' => $company->id,
                    'code' => $default['code'],
                    'name' => $default['name'],
                    'rate' => $default['rate'],
                    'type' => $default['type'],
                    'is_active' => true,
                    'is_default' => $default['is_default'] ?? false,
                    'sort_order' => $default['sort_order'],
                ]);

                $count++;
            }
        }

        return $count;
    }

    /**
     * Validate that a rate is within a sensible range.
     *
     * @throws \InvalidArgumentException
     */
    public function validateRate(float $rate): void
    {
        if ($rate < 0) {
            throw new \InvalidArgumentException('Le taux de taxe ne peut pas être négatif.');
        }

        if ($rate > 100) {
            throw new \InvalidArgumentException('Le taux de taxe ne peut pas dépasser 100%.');
        }
    }

    /**
     * Clear all defaults for a company.
     */
    private function clearDefaults(int $companyId): void
    {
        TaxRate::where('company_id', $companyId)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    /**
     * Validate that a code is unique within a company.
     *
     * @throws \InvalidArgumentException
     */
    private function validateCode(string $code, int $companyId, ?int $excludeId = null): void
    {
        $query = TaxRate::where('company_id', $companyId)
            ->where('code', strtoupper($code));

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw new \InvalidArgumentException('Un taux de taxe avec ce code existe déjà pour cette société.');
        }
    }
}
