<?php

use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyAccountingSetting;
use App\Models\Customer;
use App\Models\FiscalYear;
use App\Services\Accounting\AccountingSettingsService;
use App\Services\Accounting\TunisianTaxService;

beforeEach(function () {
    $this->company = Company::create(['name' => 'Timbre SARL', 'currency' => 'TND', 'is_active' => true]);
    $this->fiscalYear = FiscalYear::create([
        'company_id' => $this->company->id,
        'name' => 'Exercice 2026',
        'code' => '2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
        'is_closed' => false,
    ]);
    $this->customer = Customer::create([
        'company_id' => $this->company->id,
        'code' => 'CL001',
        'name' => 'Client',
        'customer_type' => 'company',
        'country' => 'TN',
        'is_active' => true,
    ]);
});

it('computes withholding amounts with millime rounding', function (string $gross, string $rate, string $expected) {
    expect(app(TunisianTaxService::class)->withholdingAmount($gross, $rate))->toBe($expected);
})->with([
    ['1000.000', '1.5', '15.000'],
    ['333.333', '1.5', '5.000'],
    ['100.001', '15', '15.000'],
    ['2000.000', '0', '0.000'],
]);

it('rejects withholding rates outside 0 to 100 percent', function () {
    app(TunisianTaxService::class)->withholdingAmount('100.000', '101');
})->throws(InvalidArgumentException::class);

it('creates a missing fiscal system account under its parent', function () {
    $parent = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => '443',
        'name' => 'État — impôts et taxes à payer',
        'account_type' => 'liability',
        'is_active' => true,
    ]);

    $service = app(TunisianTaxService::class);
    $accountId = $service->systemAccountId($this->company->id, $this->fiscalYear->id, TunisianTaxService::STAMP_DUTY);

    $account = Account::findOrFail($accountId);
    expect($account->code)->toBe('4435')
        ->and($account->parent_id)->toBe($parent->id)
        ->and($service->systemAccountId($this->company->id, $this->fiscalYear->id, TunisianTaxService::STAMP_DUTY))->toBe($accountId);
});

it('uses the configured default stamp duty for new companies', function () {
    config(['accounting.stamp_duty_default' => '1.000']);

    $settings = app(AccountingSettingsService::class)->getForCompany($this->company);

    expect((string) $settings->stamp_duty_amount)->toBe('1.000')
        ->and(app(TunisianTaxService::class)->stampDutyFor($this->customer))->toBe('1.000');
});

it('rejects a negative stamp duty in the accounting settings', function () {
    $settings = CompanyAccountingSetting::create(['company_id' => $this->company->id]);

    app(AccountingSettingsService::class)->updateSettings($settings, ['stamp_duty_amount' => '-1'], $this->company->id);
})->throws(InvalidArgumentException::class, 'droit de timbre');
