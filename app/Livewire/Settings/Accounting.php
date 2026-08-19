<?php

namespace App\Livewire\Settings;

use App\Models\Account;
use App\Models\Journal;
use App\Services\Accounting\AccountingSettingsService;
use App\Services\CurrentCompany;
use App\Services\CurrentFiscalYear;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Paramètres comptables')]
class Accounting extends Component
{
    public ?string $default_currency = 'TND';

    public int $decimal_precision = 3;

    public int $fiscal_year_start_month = 1;

    public ?int $default_sales_journal_id = null;

    public ?int $default_purchase_journal_id = null;

    public ?int $default_bank_journal_id = null;

    public ?int $default_cash_journal_id = null;

    public ?int $default_misc_journal_id = null;

    public ?int $default_customer_account_id = null;

    public ?int $default_supplier_account_id = null;

    public ?int $default_sales_account_id = null;

    public ?int $default_purchase_account_id = null;

    public ?int $default_bank_account_id = null;

    public ?int $default_cash_account_id = null;

    public ?string $invoice_prefix = 'FAC';

    public int $invoice_next_number = 1;

    public ?string $quote_prefix = 'DEV';

    public int $quote_next_number = 1;

    public function mount(CurrentCompany $currentCompany, CurrentFiscalYear $currentFiscalYear): void
    {
        $company = $currentCompany->get(Auth::user());

        if (! $company) {
            return;
        }

        $service = app(AccountingSettingsService::class);
        $settings = $service->getForCompany($company);

        $this->fill([
            'default_currency' => $settings->default_currency,
            'decimal_precision' => $settings->decimal_precision,
            'fiscal_year_start_month' => $settings->fiscal_year_start_month,
            'default_sales_journal_id' => $settings->default_sales_journal_id,
            'default_purchase_journal_id' => $settings->default_purchase_journal_id,
            'default_bank_journal_id' => $settings->default_bank_journal_id,
            'default_cash_journal_id' => $settings->default_cash_journal_id,
            'default_misc_journal_id' => $settings->default_misc_journal_id,
            'default_customer_account_id' => $settings->default_customer_account_id,
            'default_supplier_account_id' => $settings->default_supplier_account_id,
            'default_sales_account_id' => $settings->default_sales_account_id,
            'default_purchase_account_id' => $settings->default_purchase_account_id,
            'default_bank_account_id' => $settings->default_bank_account_id,
            'default_cash_account_id' => $settings->default_cash_account_id,
            'invoice_prefix' => $settings->invoice_prefix,
            'invoice_next_number' => $settings->invoice_next_number,
            'quote_prefix' => $settings->quote_prefix,
            'quote_next_number' => $settings->quote_next_number,
        ]);
    }

    public function save(CurrentCompany $currentCompany, CurrentFiscalYear $currentFiscalYear): void
    {
        $company = $currentCompany->get(Auth::user());

        if (! $company) {
            abort(403);
        }

        if (Auth::user()->cannot('update', $company->accountingSettings)) {
            abort(403);
        }

        $validated = $this->validate([
            'default_currency' => 'required|string|size:3',
            'decimal_precision' => 'required|integer|min:0|max:6',
            'fiscal_year_start_month' => 'required|integer|min:1|max:12',
            'default_sales_journal_id' => 'nullable|integer',
            'default_purchase_journal_id' => 'nullable|integer',
            'default_bank_journal_id' => 'nullable|integer',
            'default_cash_journal_id' => 'nullable|integer',
            'default_misc_journal_id' => 'nullable|integer',
            'default_customer_account_id' => 'nullable|integer',
            'default_supplier_account_id' => 'nullable|integer',
            'default_sales_account_id' => 'nullable|integer',
            'default_purchase_account_id' => 'nullable|integer',
            'default_bank_account_id' => 'nullable|integer',
            'default_cash_account_id' => 'nullable|integer',
            'invoice_prefix' => 'nullable|string|max:20',
            'invoice_next_number' => 'required|integer|min:1',
            'quote_prefix' => 'nullable|string|max:20',
            'quote_next_number' => 'required|integer|min:1',
        ]);

        $fiscalYear = $currentFiscalYear->get(Auth::user());

        $service = app(AccountingSettingsService::class);
        $settings = $service->getForCompany($company);

        try {
            $service->updateSettings(
                $settings,
                $validated,
                $company->id,
                $fiscalYear?->id,
            );
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: 'Paramètres comptables mis à jour.');
    }

    public function render(CurrentCompany $currentCompany, CurrentFiscalYear $currentFiscalYear)
    {
        $company = $currentCompany->get(Auth::user());
        $fiscalYear = $currentFiscalYear->get(Auth::user());

        $journals = collect();
        $accounts = collect();

        if ($company && $fiscalYear) {
            $journals = Journal::where('company_id', $company->id)
                ->where('fiscal_year_id', $fiscalYear->id)
                ->where('is_active', true)
                ->orderBy('code')
                ->get();

            $accounts = Account::where('company_id', $company->id)
                ->where('fiscal_year_id', $fiscalYear->id)
                ->where('is_active', true)
                ->orderBy('code')
                ->get();
        }

        return view('livewire.settings.accounting', [
            'currentCompany' => $company,
            'currentFiscalYear' => $fiscalYear,
            'journals' => $journals,
            'accounts' => $accounts,
        ]);
    }
}
