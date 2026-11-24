<?php

use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\CurrentAccountingPeriod;
use App\Services\CurrentFiscalYear;
use Carbon\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->company = Company::create(['name' => 'Nouveau client', 'currency' => 'TND', 'is_active' => true]);
    $this->company->users()->attach($this->user, ['role' => 'admin', 'is_active' => true]);
    $this->fiscalYear = FiscalYear::create([
        'company_id' => $this->company->id, 'name' => 'Exercice 2026', 'code' => '2026',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_active' => true, 'is_closed' => false,
    ]);

    foreach (range(1, 12) as $month) {
        $start = sprintf('2026-%02d-01', $month);
        AccountingPeriod::create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'name' => "Période {$month}",
            'code' => sprintf('2026-%02d', $month),
            'start_date' => $start,
            'end_date' => Carbon::parse($start)->endOfMonth()->toDateString(),
            'is_open' => true,
            'is_closed' => false,
        ]);
    }
});

it('resolves the fiscal year from the default company when nothing is selected in session', function () {
    $this->actingAs($this->user);

    expect(app(CurrentFiscalYear::class)->get($this->user)?->id)->toBe($this->fiscalYear->id);
});

it('defaults to the open accounting period containing today', function () {
    $this->travelTo('2026-11-24');
    $this->actingAs($this->user);

    expect(app(CurrentAccountingPeriod::class)->get($this->user)?->code)->toBe('2026-11');
});

it('shows the chart of accounts to a new user who never switched company', function () {
    $this->actingAs($this->user)
        ->get(route('accounts.index'))
        ->assertOk()
        ->assertDontSee('Aucune société ou exercice sélectionné');
});
