<?php

namespace App\Services\Accounting;

use App\Enums\PaymentMethodType;
use App\Models\Company;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\DB;

class PaymentMethodService
{
    /**
     * Create a new payment method.
     *
     * @param  array{code: string, name: string, type: string, is_active?: bool, is_default?: bool, sort_order?: int, description?: string|null}  $data
     *
     * @throws \InvalidArgumentException
     */
    public function createPaymentMethod(Company $company, array $data): PaymentMethod
    {
        $this->validateCode($data['code'], $company->id);

        if (! PaymentMethodType::tryFrom($data['type'])) {
            throw new \InvalidArgumentException('Le type de paiement sélectionné n\'est pas valide.');
        }

        return DB::transaction(function () use ($company, $data) {
            $isDefault = $data['is_default'] ?? false;

            if ($isDefault) {
                $this->clearDefaults($company->id);
            }

            return PaymentMethod::create([
                'company_id' => $company->id,
                'code' => strtoupper($data['code']),
                'name' => $data['name'],
                'type' => $data['type'],
                'is_active' => $data['is_active'] ?? true,
                'is_default' => $isDefault,
                'sort_order' => $data['sort_order'] ?? 0,
                'description' => $data['description'] ?? null,
            ]);
        });
    }

    /**
     * Update a payment method.
     *
     * @param  array{code: string, name: string, type: string, is_active?: bool, is_default?: bool, sort_order?: int, description?: string|null}  $data
     *
     * @throws \InvalidArgumentException
     */
    public function updatePaymentMethod(PaymentMethod $paymentMethod, array $data): PaymentMethod
    {
        $this->validateCode($data['code'], $paymentMethod->company_id, $paymentMethod->id);

        if (! PaymentMethodType::tryFrom($data['type'])) {
            throw new \InvalidArgumentException('Le type de paiement sélectionné n\'est pas valide.');
        }

        $isDefault = $data['is_default'] ?? false;
        $isActive = $data['is_active'] ?? $paymentMethod->is_active;

        if ($paymentMethod->is_default && ! $isDefault && $isActive) {
            throw new \InvalidArgumentException(
                'Impossible de retirer le statut par défaut. Veuillez d\'abord définir un autre moyen de paiement par défaut.'
            );
        }

        if ($paymentMethod->is_default && ! $isActive) {
            throw new \InvalidArgumentException(
                'Impossible de désactiver le moyen de paiement par défaut. Veuillez d\'abord définir un autre moyen de paiement par défaut.'
            );
        }

        return DB::transaction(function () use ($paymentMethod, $data, $isDefault) {
            if ($isDefault && ! $paymentMethod->is_default) {
                $this->clearDefaults($paymentMethod->company_id);
            }

            $paymentMethod->update([
                'code' => strtoupper($data['code']),
                'name' => $data['name'],
                'type' => $data['type'],
                'is_active' => $data['is_active'] ?? $paymentMethod->is_active,
                'is_default' => $isDefault,
                'sort_order' => $data['sort_order'] ?? $paymentMethod->sort_order,
                'description' => $data['description'] ?? null,
            ]);

            return $paymentMethod->fresh();
        });
    }

    /**
     * Activate a payment method.
     */
    public function activate(PaymentMethod $paymentMethod): PaymentMethod
    {
        $paymentMethod->update(['is_active' => true]);

        return $paymentMethod->fresh();
    }

    /**
     * Deactivate a payment method.
     *
     * @throws \InvalidArgumentException
     */
    public function deactivate(PaymentMethod $paymentMethod): PaymentMethod
    {
        if ($paymentMethod->is_default) {
            throw new \InvalidArgumentException(
                'Impossible de désactiver le moyen de paiement par défaut. Veuillez d\'abord définir un autre moyen de paiement par défaut.'
            );
        }

        $paymentMethod->update(['is_active' => false]);

        return $paymentMethod->fresh();
    }

    /**
     * Set a payment method as the default.
     */
    public function setDefault(PaymentMethod $paymentMethod): PaymentMethod
    {
        return DB::transaction(function () use ($paymentMethod) {
            $this->clearDefaults($paymentMethod->company_id);

            $paymentMethod->update(['is_default' => true]);

            return $paymentMethod->fresh();
        });
    }

    /**
     * Delete a payment method.
     *
     * @throws \InvalidArgumentException
     */
    public function delete(PaymentMethod $paymentMethod): void
    {
        if ($paymentMethod->is_default) {
            throw new \InvalidArgumentException(
                'Impossible de supprimer le moyen de paiement par défaut. Veuillez d\'abord définir un autre moyen de paiement par défaut.'
            );
        }

        $paymentMethod->delete();
    }

    /**
     * Initialize default payment methods for a company (idempotent).
     */
    public function initializeDefaults(Company $company): int
    {
        $defaults = [
            ['code' => 'CASH', 'name' => 'Espèces', 'type' => PaymentMethodType::CASH, 'sort_order' => 1, 'is_default' => true],
            ['code' => 'BANK', 'name' => 'Virement bancaire', 'type' => PaymentMethodType::BANK_TRANSFER, 'sort_order' => 2],
            ['code' => 'CHQ', 'name' => 'Chèque', 'type' => PaymentMethodType::CHEQUE, 'sort_order' => 3],
            ['code' => 'CARD', 'name' => 'Carte bancaire', 'type' => PaymentMethodType::CARD, 'sort_order' => 4],
            ['code' => 'OTHER', 'name' => 'Autre', 'type' => PaymentMethodType::OTHER, 'sort_order' => 5],
        ];

        $existingCodes = PaymentMethod::where('company_id', $company->id)
            ->pluck('code')
            ->toArray();

        $count = 0;

        foreach ($defaults as $default) {
            if (! in_array($default['code'], $existingCodes)) {
                PaymentMethod::create([
                    'company_id' => $company->id,
                    'code' => $default['code'],
                    'name' => $default['name'],
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
     * Clear all defaults for a company.
     */
    private function clearDefaults(int $companyId): void
    {
        PaymentMethod::where('company_id', $companyId)
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
        $query = PaymentMethod::where('company_id', $companyId)
            ->where('code', strtoupper($code));

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw new \InvalidArgumentException('Un moyen de paiement avec ce code existe déjà pour cette société.');
        }
    }
}
