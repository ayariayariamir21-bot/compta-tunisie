<?php

namespace App\Livewire\Quotes;

use App\Enums\QuoteStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quote;
use App\Models\TaxRate;
use App\Services\CurrentCompany;
use App\Services\QuoteService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Edit extends Component
{
    public ?int $quoteId = null;

    public ?int $customer_id = null;

    public string $quote_date = '';

    public ?string $valid_until = null;

    public string $currency = 'TND';

    public int $payment_terms_days = 0;

    public ?string $notes = null;

    public ?string $terms = null;

    /** @var array<int, array{product_id: int|null, description: string, quantity: string, unit: string, unit_price: string, discount_percent: string, tax_rate_id: int|null, _key: string}> */
    public array $lines = [];

    private int $lineCounter = 0;

    public function mount(int $quoteId): void
    {
        $quote = Quote::with('lines')->findOrFail($quoteId);

        $company = app(CurrentCompany::class)->get(Auth::user());

        if (! $company || $quote->company_id !== $company->id) {
            abort(403);
        }

        if ($quote->status->value !== QuoteStatus::DRAFT->value) {
            abort(403, 'Seul un devis en brouillon peut être modifié.');
        }

        if (Auth::user()->cannot('update', $quote)) {
            abort(403);
        }

        $this->quoteId = $quote->id;
        $this->customer_id = $quote->customer_id;
        $this->quote_date = $quote->quote_date->toDateString();
        $this->valid_until = $quote->valid_until?->toDateString();
        $this->currency = $quote->currency;
        $this->payment_terms_days = $quote->payment_terms_days;
        $this->notes = $quote->notes;
        $this->terms = $quote->terms;

        $this->lines = [];
        foreach ($quote->lines as $line) {
            $this->lineCounter++;
            $this->lines[] = [
                'product_id' => $line->product_id,
                'description' => $line->description,
                'quantity' => (string) $line->quantity,
                'unit' => $line->unit,
                'unit_price' => (string) $line->unit_price,
                'discount_percent' => (string) $line->discount_percent,
                'tax_rate_id' => $line->tax_rate_id,
                '_key' => uniqid('line_', true),
            ];
        }

        if (empty($this->lines)) {
            $this->addLine();
        }
    }

    /** @return array<string, list<string|ValidationRule>> */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer'],
            'quote_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:quote_date'],
            'currency' => ['required', 'string', 'size:3'],
            'payment_terms_days' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'terms' => ['nullable', 'string', 'max:5000'],
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
            'customer_id' => 'le client',
            'quote_date' => 'la date du devis',
            'valid_until' => 'la date de validité',
            'currency' => 'la devise',
            'payment_terms_days' => 'les conditions de paiement',
            'notes' => 'les notes',
            'terms' => 'les conditions générales',
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

        if ($product->sale_price) {
            $line['unit_price'] = (string) $product->sale_price;
        }

        if ($product->tax_rate_id) {
            $line['tax_rate_id'] = $product->tax_rate_id;
        }

        $this->lines[$index] = $line;
    }

    public function update(QuoteService $quoteService, CurrentCompany $currentCompany): void
    {
        $company = $currentCompany->get(Auth::user());

        if (! $company) {
            session()->flash('error', 'Aucune société sélectionnée.');

            return;
        }

        $quote = Quote::findOrFail($this->quoteId);

        if (Auth::user()->cannot('update', $quote)) {
            abort(403);
        }

        if ($quote->company_id !== $company->id) {
            abort(403);
        }

        $validated = $this->validate();

        $validated['customer_id'] = (int) $validated['customer_id'];
        $validated['payment_terms_days'] = (int) $validated['payment_terms_days'];
        $validated['currency'] = strtoupper($validated['currency']);
        $validated['valid_until'] = $validated['valid_until'] ?: null;

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
            $quoteService->updateDraft($quote, $validated);
        } catch (\InvalidArgumentException $e) {
            $this->addError('customer_id', $e->getMessage());

            return;
        }

        session()->flash('success', "Le devis « {$quote->quote_number} » a été modifié.");
        $this->redirect(route('quotes.index'), navigate: true);
    }

    public function render(CurrentCompany $currentCompany): View
    {
        $company = $currentCompany->get(Auth::user());
        $customers = collect();
        $products = collect();
        $taxRates = collect();

        if ($company) {
            $customers = Customer::where('company_id', $company->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            $products = Product::where('company_id', $company->id)
                ->where('is_active', true)
                ->where('is_sellable', true)
                ->orderBy('code')
                ->get();

            $taxRates = TaxRate::where('company_id', $company->id)
                ->where('is_active', true)
                ->orderBy('code')
                ->get();
        }

        return view('livewire.quotes.edit', [
            'customers' => $customers,
            'products' => $products,
            'taxRates' => $taxRates,
            'quoteStatuses' => QuoteStatus::cases(),
        ]);
    }
}
