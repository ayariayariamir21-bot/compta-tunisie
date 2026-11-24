<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\CompanyAccountingSetting;
use App\Models\Customer;
use App\Models\TaxRate;
use App\Support\Decimal;

/**
 * Tunisian fiscal rules shared by sales and payment flows:
 * droit de timbre on invoices and retenue à la source on payments.
 */
class TunisianTaxService
{
    public const STAMP_DUTY = 'stamp_duty';

    public const WITHHOLDING_PAYABLE = 'withholding_payable';

    public const WITHHOLDING_RECEIVABLE = 'withholding_receivable';

    public const VAT_COLLECTED = 'vat_collected';

    public const VAT_DEDUCTIBLE = 'vat_deductible';

    /**
     * Stamp duty to apply on a new or edited sales invoice for this customer.
     *
     * @return numeric-string
     */
    public function stampDutyFor(Customer $customer): string
    {
        if ($customer->is_stamp_duty_exempt) {
            return '0.000';
        }

        $settings = CompanyAccountingSetting::where('company_id', $customer->company_id)->first();

        $amount = $settings !== null
            ? Decimal::round((string) $settings->stamp_duty_amount)
            : Decimal::round((string) config('accounting.stamp_duty_default', '0'));

        if (bccomp($amount, '0', 3) < 0) {
            throw new \InvalidArgumentException('Le montant du droit de timbre ne peut pas être négatif.');
        }

        return $amount;
    }

    /**
     * Withholding amount for a gross payment and a rate in percent, rounded to the millime.
     *
     * @return numeric-string
     */
    public function withholdingAmount(string $grossAmount, string $ratePercent): string
    {
        $gross = Decimal::round($grossAmount);
        $rate = Decimal::round($ratePercent);

        if (bccomp($rate, '0', 3) < 0 || bccomp($rate, '100', 3) > 0) {
            throw new \InvalidArgumentException('Le taux de retenue à la source doit être compris entre 0 et 100 %.');
        }

        return Decimal::round(bcdiv(bcmul($gross, $rate, 6), '100', 6));
    }

    /**
     * VAT account of a tax rate inside the document's fiscal year.
     *
     * Accounts belong to one fiscal year, while tax rates live at company
     * level: the account configured on the rate is matched by its code in
     * the document's fiscal year, so a 2027 invoice never posts to a 2026
     * account. Without configuration, the standard account (445 collected /
     * 444 deductible) of that fiscal year is used and created if missing.
     *
     * @param  'sales'|'purchase'  $direction
     */
    public function vatAccountId(?TaxRate $taxRate, int $companyId, int $fiscalYearId, string $direction): int
    {
        $configuredId = $direction === 'sales' ? $taxRate?->sales_tax_account_id : $taxRate?->purchase_tax_account_id;

        if ($configuredId !== null) {
            $code = Account::whereKey($configuredId)->where('company_id', $companyId)->value('code');

            if ($code !== null) {
                $account = Account::where('company_id', $companyId)
                    ->where('fiscal_year_id', $fiscalYearId)
                    ->where('code', $code)
                    ->where('is_active', true)
                    ->first();

                if ($account !== null) {
                    return $account->id;
                }
            }
        }

        return $this->systemAccountId(
            $companyId,
            $fiscalYearId,
            $direction === 'sales' ? self::VAT_COLLECTED : self::VAT_DEDUCTIBLE,
        );
    }

    /**
     * Codes of every account that can hold VAT for the company (configured on
     * a tax rate in any fiscal year, or the standard 445/444).
     *
     * @param  'sales'|'purchase'  $direction
     * @return list<string>
     */
    public function vatAccountCodes(int $companyId, string $direction): array
    {
        $column = $direction === 'sales' ? 'sales_tax_account_id' : 'purchase_tax_account_id';
        $configuredIds = TaxRate::where('company_id', $companyId)->whereNotNull($column)->pluck($column);

        /** @var list<string> $codes */
        $codes = Account::where('company_id', $companyId)->whereIn('id', $configuredIds)->pluck('code')->map(fn (mixed $code): string => (string) $code)->all();

        /** @var array{code: string} $default */
        $default = config('accounting.system_accounts.'.($direction === 'sales' ? self::VAT_COLLECTED : self::VAT_DEDUCTIBLE));
        $codes[] = $default['code'];

        return array_values(array_unique($codes));
    }

    /**
     * Resolve (and create when missing) a fiscal system account in the fiscal year.
     */
    public function systemAccountId(int $companyId, int $fiscalYearId, string $key): int
    {
        /** @var array{code: string, name: string, type: string, parent: string}|null $definition */
        $definition = config("accounting.system_accounts.{$key}");

        if ($definition === null) {
            throw new \InvalidArgumentException("Compte système inconnu : {$key}.");
        }

        $existing = Account::where('company_id', $companyId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('code', $definition['code'])
            ->first();

        if ($existing !== null) {
            if (! $existing->is_active) {
                throw new \InvalidArgumentException("Le compte {$definition['code']} ({$definition['name']}) est inactif. Veuillez le réactiver.");
            }

            return $existing->id;
        }

        $parentId = Account::where('company_id', $companyId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('code', $definition['parent'])
            ->value('id');

        return Account::create([
            'company_id' => $companyId,
            'fiscal_year_id' => $fiscalYearId,
            'parent_id' => $parentId,
            'code' => $definition['code'],
            'name' => $definition['name'],
            'account_type' => AccountType::from($definition['type']),
            'is_active' => true,
        ])->id;
    }
}
