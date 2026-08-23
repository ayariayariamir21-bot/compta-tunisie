<?php

namespace App\Livewire\PurchaseInvoices;

use App\Models\Journal;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Services\CurrentAccountingPeriod;
use App\Services\CurrentCompany;
use App\Services\CurrentFiscalYear;
use App\Services\PurchaseInvoiceService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Nouvelle facture fournisseur')]
class Create extends Component
{
    public ?int $supplier_id = null;

    public string $invoice_date = '';

    public ?string $due_date = null;

    public ?string $supplier_invoice_number = null;

    public string $currency = 'TND';

    public ?int $journal_id = null;

    public int $payment_terms_days = 0;

    public ?string $notes = null;

    /** @var array<int, array{product_id: int|null, description: string, quantity: string, unit: string, unit_price: string, discount_percent: string, tax_rate_id: int|null, _key: string}> */
    public array $lines = [];

    public bool $saveAndPost = false;

    private int $lineCounter = 0;

    public function mount(): void
    {
        $this->invoice_date = now()->toDateString();
        $this->addLine();
    }

    /** @return array<string, list<string|ValidationRule>> */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'supplier_invoice_number' => ['nullable', 'string', 'max:100'],
            'currency' => ['required', 'string', 'size:3'],
            'journal_id' => ['required', 'integer'],
            'payment_terms_days' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'min:1'],
            'lines.*.product_id' => ['required', 'integer'],
            'lines.*.description' => ['nullable', 'string', 'max:500'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit' => ['required', 'string', 'max:50'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'lines.*.tax_rate_id' => ['nullable', 'integer'],
        ];
    }

    /** @return array<string, string> */
    public function validationAttributes(): array
    {
        return [
            'supplier_id' => 'le fournisseur',
            'invoice_date' => 'la date de facture',
            'due_date' => 'la date d\'échéance',
            'supplier_invoice_number' => 'le numéro de facture du fournisseur',
            'currency' => 'la devise',
            'journal_id' => 'le journal',
            'payment_terms_days' => 'les conditions de paiement',
            'notes' => 'les notes',
            'lines' => 'les lignes',
            'lines.*.product_id' => 'le produit',
            'lines.*.description' => 'la description',
            'lines.*.quantity' => 'la quantité',
            'lines.*.unit' => 'l\'unité',
            'lines.*.unit_price' => 'le prix unitaire',
            'lines.*.discount_percent' => 'la remise',
            'lines.*.tax_rate_id' => 'le taux de TVA',
        ];
    }

    public function addLine(): void
    {
        $this->lineCounter++;
        $line = [
            'product_id' => null,
            'description' => '',
            'quantity' => '1.000',
            'unit' => 'unit',
            'unit_price' => '0.000',
            'discount_percent' => '0.000',
            'tax_rate_id' => null,
            '_key' => uniqid('line_', true),
        ];
        $this->lines[] = $line;
    }

    public function removeLine(int $index): void
    {
        if (count($this->lines) > 1) {
            unset($this->lines[$index]);
            $this->lines = array_values($this->lines);
        }
    }

    public function onSupplierSelect(int $supplierId): void
    {
        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        if (! $currentCompany) {
            return;
        }

        $supplier = Supplier::where('id', $supplierId)
            ->where('company_id', $currentCompany->id)
            ->first();

        if (! $supplier) {
            return;
        }

        $this->payment_terms_days = $supplier->payment_terms_days ?? 0;
    }

    public function onProductSelect(int $index, int $productId): void
    {
        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        if (! $currentCompany) {
            return;
        }

        $product = Product::where('id', $productId)
            ->where('company_id', $currentCompany->id)
            ->first();

        if (! $product) {
            return;
        }

        $line = $this->lines[$index];
        $line['description'] = $product->description ?? $product->name;
        $line['unit'] = $product->unit;

        if ($product->purchase_price) {
            $line['unit_price'] = (string) $product->purchase_price;
        }

        if ($product->tax_rate_id) {
            $line['tax_rate_id'] = $product->tax_rate_id;
        }

        $this->lines[$index] = $line;
    }

    public function store(PurchaseInvoiceService $purchaseInvoiceService, CurrentCompany $currentCompany, CurrentFiscalYear $currentFiscalYear, CurrentAccountingPeriod $currentAccountingPeriod): void
    {
        $company = $currentCompany->get(Auth::user());
        $fiscalYear = $currentFiscalYear->get(Auth::user());
        $accountingPeriod = $currentAccountingPeriod->get(Auth::user());

        if (! $company || ! $fiscalYear || ! $accountingPeriod) {
            session()->flash('error', 'Contexte comptable incomplet. Veuillez sélectionner une société, un exercice et une période.');

            return;
        }

        if (Auth::user()->cannot('create', [PurchaseInvoice::class, $company])) {
            abort(403);
        }

        $validated = $this->validate();

        $validated['company_id'] = $company->id;
        $validated['fiscal_year_id'] = $fiscalYear->id;
        $validated['accounting_period_id'] = $accountingPeriod->id;
        $validated['created_by'] = Auth::id();
        $validated['supplier_id'] = (int) $validated['supplier_id'];
        $validated['journal_id'] = (int) $validated['journal_id'];
        $validated['payment_terms_days'] = (int) $validated['payment_terms_days'];
        $validated['currency'] = strtoupper($validated['currency']);
        $validated['due_date'] = $validated['due_date'] ?: null;
        $validated['supplier_invoice_number'] = $validated['supplier_invoice_number'] ?: null;

        $linesData = [];
        foreach ($validated['lines'] as $line) {
            $linesData[] = [
                'product_id' => (int) $line['product_id'],
                'description' => $line['description'] ?? '',
                'quantity' => (string) $line['quantity'],
                'unit' => $line['unit'],
                'unit_price' => (string) $line['unit_price'],
                'discount_percent' => (string) $line['discount_percent'],
                'tax_rate_id' => $line['tax_rate_id'] ? (int) $line['tax_rate_id'] : null,
            ];
        }

        $validated['lines'] = $linesData;

        try {
            $purchaseInvoice = $purchaseInvoiceService->createDraft($validated);

            if ($this->saveAndPost) {
                $purchaseInvoiceService->post($purchaseInvoice, (int) Auth::id());
            }
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $this->addError('supplier_id', $e->getMessage());

            return;
        }

        $label = $this->saveAndPost ? 'créée et comptabilisée' : 'créée';
        session()->flash('success', "La facture fournisseur « {$purchaseInvoice->invoice_number} » a été {$label}.");
        $this->redirect(route('purchase-invoices.index'), navigate: true);
    }

    public function render(CurrentCompany $currentCompany): View
    {
        $company = $currentCompany->get(Auth::user());
        $suppliers = collect();
        $products = collect();
        $taxRates = collect();
        $journals = collect();

        if ($company) {
            $fy = app(CurrentFiscalYear::class)->get(Auth::user());

            $suppliers = Supplier::where('company_id', $company->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            $products = Product::where('company_id', $company->id)
                ->where('is_active', true)
                ->where('is_purchasable', true)
                ->orderBy('code')
                ->get();

            $taxRates = TaxRate::where('company_id', $company->id)
                ->where('is_active', true)
                ->orderBy('code')
                ->get();

            $journalQuery = Journal::where('company_id', $company->id)
                ->where('is_active', true)
                ->where('type', 'achats')
                ->orderBy('code');

            if ($fy) {
                $journalQuery->where('fiscal_year_id', $fy->id);
            }

            $journals = $journalQuery->get();
        }

        return view('livewire.purchase-invoices.create', [
            'suppliers' => $suppliers,
            'products' => $products,
            'taxRates' => $taxRates,
            'journals' => $journals,
        ]);
    }
}
