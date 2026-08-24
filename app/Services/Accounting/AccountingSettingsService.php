<?php

namespace App\Services\Accounting;

use App\Enums\AuditAction;
use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyAccountingSetting;
use App\Models\Journal;
use App\Services\Security\AuditLogService;
use Illuminate\Support\Facades\DB;

class AccountingSettingsService
{
    public function __construct(

    ) {}

    private function audits(): AuditLogService
    {
        return $this->auditLog ?? new AuditLogService;
    }

    /**
     * Get or create accounting settings for a company.
     */
    public function getForCompany(Company $company): CompanyAccountingSetting
    {
        $settings = CompanyAccountingSetting::where('company_id', $company->id)->first();

        if (! $settings) {
            $settings = $this->createDefaultSettings($company);
        }

        return $settings;
    }

    /**
     * Create default accounting settings for a company.
     */
    public function createDefaultSettings(Company $company): CompanyAccountingSetting
    {
        return DB::transaction(function () use ($company) {
            return CompanyAccountingSetting::create([
                'company_id' => $company->id,
                'default_currency' => $company->currency ?? 'TND',
                'decimal_precision' => 3,
                'fiscal_year_start_month' => 1,
                'invoice_prefix' => 'FAC',
                'invoice_next_number' => 1,
                'quote_prefix' => 'DEV',
                'quote_next_number' => 1,
            ]);
        });
    }

    /**
     * Update accounting settings for a company.
     *
     * @param  array{default_currency?: string, decimal_precision?: int, fiscal_year_start_month?: int, default_sales_journal_id?: int|null, default_purchase_journal_id?: int|null, default_bank_journal_id?: int|null, default_cash_journal_id?: int|null, default_misc_journal_id?: int|null, default_customer_account_id?: int|null, default_supplier_account_id?: int|null, default_sales_account_id?: int|null, default_purchase_account_id?: int|null, default_bank_account_id?: int|null, default_cash_account_id?: int|null, invoice_prefix?: string, invoice_next_number?: int, quote_prefix?: string, quote_next_number?: int}  $data
     *
     * @throws \InvalidArgumentException
     */
    public function updateSettings(CompanyAccountingSetting $settings, array $data, int $companyId, ?int $fiscalYearId = null): CompanyAccountingSetting
    {
        if ($settings->company_id !== $companyId) {
            throw new \InvalidArgumentException('Les paramètres n\'appartiennent pas à cette société.');
        }

        $validated = $this->validateDefaults($data, $companyId, $fiscalYearId);

        $before = $this->audits()->snapshot($settings, array_keys($validated));

        DB::transaction(function () use ($settings, $validated, $before): void {
            $settings->update($validated);

            $this->audits()->logModelUpdated(
                $settings,
                AuditAction::SettingsUpdated,
                $before,
                $this->audits()->snapshot($settings, array_keys($validated)),
            );
        });

        return $settings->fresh();
    }

    /**
     * Validate default journal and account references.
     *
     * @param  array{default_currency?: string, decimal_precision?: int, fiscal_year_start_month?: int, default_sales_journal_id?: int|null, default_purchase_journal_id?: int|null, default_bank_journal_id?: int|null, default_cash_journal_id?: int|null, default_misc_journal_id?: int|null, default_customer_account_id?: int|null, default_supplier_account_id?: int|null, default_sales_account_id?: int|null, default_purchase_account_id?: int|null, default_bank_account_id?: int|null, default_cash_account_id?: int|null, invoice_prefix?: string, invoice_next_number?: int, quote_prefix?: string, quote_next_number?: int}  $data
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException
     */
    public function validateDefaults(array $data, int $companyId, ?int $fiscalYearId = null): array
    {
        $journalFields = [
            'default_sales_journal_id',
            'default_purchase_journal_id',
            'default_bank_journal_id',
            'default_cash_journal_id',
            'default_misc_journal_id',
        ];

        foreach ($journalFields as $field) {
            if (! empty($data[$field])) {
                $query = Journal::where('id', $data[$field])
                    ->where('company_id', $companyId);

                if ($fiscalYearId) {
                    $query->where('fiscal_year_id', $fiscalYearId);
                }

                if (! $query->exists()) {
                    throw new \InvalidArgumentException(
                        "Le journal sélectionné pour « {$this->fieldLabel($field)} » n\'appartient pas à cette société."
                    );
                }
            }
        }

        $accountFields = [
            'default_customer_account_id',
            'default_supplier_account_id',
            'default_sales_account_id',
            'default_purchase_account_id',
            'default_bank_account_id',
            'default_cash_account_id',
        ];

        foreach ($accountFields as $field) {
            if (! empty($data[$field])) {
                $query = Account::where('id', $data[$field])
                    ->where('company_id', $companyId)
                    ->where('is_active', true);

                if ($fiscalYearId) {
                    $query->where('fiscal_year_id', $fiscalYearId);
                }

                if (! $query->exists()) {
                    throw new \InvalidArgumentException(
                        "Le compte sélectionné pour « {$this->fieldLabel($field)} » n\'appartient pas à cette société."
                    );
                }
            }
        }

        return $data;
    }

    /**
     * Get human-readable label for a field.
     */
    private function fieldLabel(string $field): string
    {
        return match ($field) {
            'default_sales_journal_id' => 'Journal des ventes',
            'default_purchase_journal_id' => 'Journal des achats',
            'default_bank_journal_id' => 'Journal banque',
            'default_cash_journal_id' => 'Journal caisse',
            'default_misc_journal_id' => 'Journal opérations diverses',
            'default_customer_account_id' => 'Compte clients',
            'default_supplier_account_id' => 'Compte fournisseurs',
            'default_sales_account_id' => 'Compte ventes',
            'default_purchase_account_id' => 'Compte achats',
            'default_bank_account_id' => 'Compte banque',
            'default_cash_account_id' => 'Compte caisse',
            default => $field,
        };
    }
}
