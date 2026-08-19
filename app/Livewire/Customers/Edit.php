<?php

namespace App\Livewire\Customers;

use App\Enums\CustomerType;
use App\Models\Account;
use App\Models\Customer;
use App\Services\CurrentCompany;
use App\Services\CurrentFiscalYear;
use App\Services\CustomerService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Edit extends Component
{
    public ?Customer $customer = null;

    public string $code = '';

    public string $name = '';

    public ?string $legal_name = null;

    public ?string $customer_type = null;

    public ?string $tax_identifier = null;

    public ?string $rne = null;

    public ?string $address = null;

    public ?string $postal_code = null;

    public ?string $city = null;

    public ?string $governorate = null;

    public string $country = 'TN';

    public ?string $phone = null;

    public ?string $mobile = null;

    public ?string $email = null;

    public ?string $website = null;

    public int $payment_terms_days = 0;

    public ?string $credit_limit = null;

    public ?int $account_id = null;

    public ?string $notes = null;

    public bool $is_active = true;

    public function mount(int $customerId, CurrentCompany $currentCompany): void
    {
        $customer = Customer::findOrFail($customerId);

        $company = $currentCompany->get(Auth::user());

        if (! $company || $customer->company_id !== $company->id) {
            abort(403);
        }

        if (Auth::user()->cannot('update', $customer)) {
            abort(403);
        }

        $this->customer = $customer;
        $this->code = $customer->code;
        $this->name = $customer->name;
        $this->legal_name = $customer->legal_name;
        $this->customer_type = $customer->customer_type;
        $this->tax_identifier = $customer->tax_identifier;
        $this->rne = $customer->rne;
        $this->address = $customer->address;
        $this->postal_code = $customer->postal_code;
        $this->city = $customer->city;
        $this->governorate = $customer->governorate;
        $this->country = $customer->country;
        $this->phone = $customer->phone;
        $this->mobile = $customer->mobile;
        $this->email = $customer->email;
        $this->website = $customer->website;
        $this->payment_terms_days = $customer->payment_terms_days;
        $this->credit_limit = $customer->credit_limit !== null ? (string) $customer->credit_limit : null;
        $this->account_id = $customer->account_id;
        $this->notes = $customer->notes;
        $this->is_active = $customer->is_active;
    }

    /** @return array<string, list<string|ValidationRule>> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'customer_type' => ['required', 'enum:'.CustomerType::class],
            'tax_identifier' => ['nullable', 'string', 'max:50'],
            'rne' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
            'governorate' => ['nullable', 'string', 'max:100'],
            'country' => ['required', 'string', 'max:2'],
            'phone' => ['nullable', 'string', 'max:50'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'payment_terms_days' => ['required', 'integer', 'min:0'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'account_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function validationAttributes(): array
    {
        return [
            'code' => 'le code',
            'name' => 'le nom',
            'legal_name' => 'la raison sociale',
            'customer_type' => 'le type de client',
            'tax_identifier' => 'l\'identifiant fiscal',
            'rne' => 'le RNE',
            'address' => 'l\'adresse',
            'postal_code' => 'le code postal',
            'city' => 'la ville',
            'governorate' => 'le gouvernorat',
            'country' => 'le pays',
            'phone' => 'le téléphone',
            'mobile' => 'le mobile',
            'email' => 'l\'email',
            'website' => 'le site web',
            'payment_terms_days' => 'les délais de paiement',
            'credit_limit' => 'la limite de crédit',
            'account_id' => 'le compte comptable',
            'notes' => 'les notes',
            'is_active' => 'l\'état',
        ];
    }

    public function update(CurrentCompany $currentCompany, CurrentFiscalYear $currentFiscalYear, CustomerService $customerService): void
    {
        $company = $currentCompany->get(Auth::user());
        $fiscalYear = $currentFiscalYear->get(Auth::user());

        if (! $company || ! $this->customer || $this->customer->company_id !== $company->id) {
            abort(403);
        }

        if (Auth::user()->cannot('update', $this->customer)) {
            abort(403);
        }

        $validated = $this->validate();

        $validated['fiscal_year_id'] = $fiscalYear?->id;
        $validated['code'] = strtoupper($validated['code']);
        $validated['credit_limit'] = $validated['credit_limit'] !== null ? (string) $validated['credit_limit'] : null;
        $validated['account_id'] = $validated['account_id'] ?? null;

        try {
            $customerService->updateCustomer($this->customer, $validated);
        } catch (\InvalidArgumentException $e) {
            $this->addError('code', $e->getMessage());

            return;
        }

        session()->flash('success', "Le client « {$this->name} » a été mis à jour.");

        $this->redirect(route('customers.index'), navigate: true);
    }

    public function render(CurrentFiscalYear $currentFiscalYear): View
    {
        $fiscalYear = $currentFiscalYear->get(Auth::user());
        $company = app(CurrentCompany::class)->get(Auth::user());

        $accounts = collect();

        if ($company && $fiscalYear) {
            $accounts = Account::where('company_id', $company->id)
                ->where('fiscal_year_id', $fiscalYear->id)
                ->where('is_active', true)
                ->orderBy('code')
                ->get();
        }

        return view('livewire.customers.edit', [
            'customerTypes' => CustomerType::cases(),
            'accounts' => $accounts,
        ]);
    }
}
