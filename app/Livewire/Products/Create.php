<?php

namespace App\Livewire\Products;

use App\Enums\ProductType;
use App\Models\Account;
use App\Models\Product;
use App\Models\TaxRate;
use App\Services\CurrentCompany;
use App\Services\CurrentFiscalYear;
use App\Services\ProductService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Create extends Component
{
    public string $code = '';

    public string $name = '';

    public ?string $type = null;

    public ?string $description = null;

    public string $unit = 'unit';

    public ?string $purchase_price = null;

    public ?string $sale_price = null;

    public ?int $tax_rate_id = null;

    public ?int $sales_account_id = null;

    public ?int $purchase_account_id = null;

    public bool $is_active = true;

    public bool $is_sellable = true;

    public bool $is_purchasable = true;

    public bool $isGenerated = false;

    /** @return array<string, list<string|ValidationRule>> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'enum:'.ProductType::class],
            'description' => ['nullable', 'string', 'max:5000'],
            'unit' => ['required', 'string', 'max:50'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'tax_rate_id' => ['nullable', 'integer'],
            'sales_account_id' => ['nullable', 'integer'],
            'purchase_account_id' => ['nullable', 'integer'],
            'is_active' => ['boolean'],
            'is_sellable' => ['boolean'],
            'is_purchasable' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function validationAttributes(): array
    {
        return [
            'code' => 'le code',
            'name' => 'le nom',
            'type' => 'le type',
            'description' => 'la description',
            'unit' => 'l\'unité',
            'purchase_price' => 'le prix d\'achat',
            'sale_price' => 'le prix de vente',
            'tax_rate_id' => 'le taux de TVA',
            'sales_account_id' => 'le compte de vente',
            'purchase_account_id' => 'le compte d\'achat',
            'is_active' => 'l\'état',
            'is_sellable' => 'la vente',
            'is_purchasable' => 'l\'achat',
        ];
    }

    public function generateCode(CurrentCompany $currentCompany, ProductService $productService): void
    {
        $company = $currentCompany->get(Auth::user());
        if (! $company) {
            return;
        }

        $this->code = $productService->generateCode($company->id);
        $this->isGenerated = true;
    }

    public function store(CurrentCompany $currentCompany, CurrentFiscalYear $currentFiscalYear, ProductService $productService): void
    {
        $company = $currentCompany->get(Auth::user());
        $fiscalYear = $currentFiscalYear->get(Auth::user());

        if (! $company) {
            session()->flash('error', 'Aucune société sélectionnée.');

            return;
        }

        if (! $fiscalYear) {
            session()->flash('error', 'Aucun exercice comptable sélectionné.');

            return;
        }

        if (Auth::user()->cannot('create', [Product::class, $company])) {
            abort(403);
        }

        $validated = $this->validate();

        $validated['company_id'] = $company->id;
        $validated['fiscal_year_id'] = $fiscalYear->id;
        $validated['code'] = strtoupper($validated['code']);
        $validated['purchase_price'] = $validated['purchase_price'] !== null ? (string) $validated['purchase_price'] : null;
        $validated['sale_price'] = $validated['sale_price'] !== null ? (string) $validated['sale_price'] : null;
        $validated['tax_rate_id'] = $validated['tax_rate_id'] ?? null;
        $validated['sales_account_id'] = $validated['sales_account_id'] ?? null;
        $validated['purchase_account_id'] = $validated['purchase_account_id'] ?? null;

        try {
            $productService->createProduct($validated);
        } catch (\InvalidArgumentException $e) {
            $this->addError('code', $e->getMessage());

            return;
        }

        session()->flash('success', "Le produit/service « {$this->name} » a été créé.");
        $this->redirect(route('products.index'), navigate: true);
    }

    public function render(CurrentCompany $currentCompany, CurrentFiscalYear $currentFiscalYear): View
    {
        $fiscalYear = $currentFiscalYear->get(Auth::user());
        $company = $currentCompany->get(Auth::user());

        $accounts = collect();
        $taxRates = collect();

        if ($company && $fiscalYear) {
            $accounts = Account::where('company_id', $company->id)
                ->where('fiscal_year_id', $fiscalYear->id)
                ->where('is_active', true)
                ->orderBy('code')
                ->get();

            $taxRates = TaxRate::where('company_id', $company->id)
                ->where('is_active', true)
                ->orderBy('code')
                ->get();
        }

        return view('livewire.products.create', [
            'productTypes' => ProductType::cases(),
            'accounts' => $accounts,
            'taxRates' => $taxRates,
        ]);
    }
}
