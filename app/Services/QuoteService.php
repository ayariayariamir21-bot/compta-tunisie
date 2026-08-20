<?php

namespace App\Services;

use App\Enums\QuoteStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\TaxRate;
use Illuminate\Support\Facades\DB;

class QuoteService
{
    /**
     * @param  array{company_id: int, customer_id: int, quote_date: string, valid_until?: string|null, currency: string, payment_terms_days: int, notes?: string|null, terms?: string|null, created_by: int, lines: array<int, array{product_id: int, description: string, quantity: string, unit: string, unit_price: string, discount_percent: string, tax_rate_id?: int|null}>}  $data
     */
    public function createDraft(array $data): Quote
    {
        $companyId = $data['company_id'];
        $this->validateCustomer((int) $data['customer_id'], $companyId);
        $this->validateDates($data['quote_date'], $data['valid_until'] ?? null);
        $this->validateLines($data['lines']);

        $data['quote_number'] = $this->generateQuoteNumber($companyId);
        $data['status'] = QuoteStatus::DRAFT;

        $linesData = $data['lines'];
        unset($data['lines']);

        return DB::transaction(function () use ($data, $linesData, $companyId) {
            $quote = Quote::create($data);

            $this->syncLines($quote, $linesData, $companyId);

            $this->calculateTotals($quote);

            return $quote->fresh(['lines.product', 'lines.taxRate', 'customer']);
        });
    }

    /**
     * @param  array{customer_id: int, quote_date: string, valid_until?: string|null, currency: string, payment_terms_days: int, notes?: string|null, terms?: string|null, lines: array<int, array{product_id: int, description: string, quantity: string, unit: string, unit_price: string, discount_percent: string, tax_rate_id?: int|null}>}  $data
     */
    public function updateDraft(Quote $quote, array $data): Quote
    {
        if ($quote->status !== QuoteStatus::DRAFT) {
            throw new \InvalidArgumentException('Seul un devis en brouillon peut être modifié.');
        }

        $companyId = $quote->company_id;
        $this->validateCustomer((int) $data['customer_id'], $companyId);
        $this->validateDates($data['quote_date'], $data['valid_until'] ?? null);
        $this->validateLines($data['lines']);

        $linesData = $data['lines'];
        unset($data['lines']);

        return DB::transaction(function () use ($quote, $data, $linesData, $companyId) {
            $quote->update($data);

            $this->syncLines($quote, $linesData, $companyId);

            $this->calculateTotals($quote);

            return $quote->fresh(['lines.product', 'lines.taxRate', 'customer']);
        });
    }

    public function send(Quote $quote): Quote
    {
        if ($quote->status !== QuoteStatus::DRAFT) {
            throw new \InvalidArgumentException('Seul un devis en brouillon peut être envoyé.');
        }

        if ($quote->lines()->count() === 0) {
            throw new \InvalidArgumentException('Un devis doit contenir au moins une ligne.');
        }

        $quote->update(['status' => QuoteStatus::SENT]);

        return $quote->fresh();
    }

    public function accept(Quote $quote): Quote
    {
        if ($quote->status !== QuoteStatus::SENT) {
            throw new \InvalidArgumentException('Seul un devis envoyé peut être accepté.');
        }

        $quote->update(['status' => QuoteStatus::ACCEPTED]);

        return $quote->fresh();
    }

    public function reject(Quote $quote): Quote
    {
        if ($quote->status !== QuoteStatus::SENT) {
            throw new \InvalidArgumentException('Seul un devis envoyé peut être refusé.');
        }

        $quote->update(['status' => QuoteStatus::REJECTED]);

        return $quote->fresh();
    }

    public function cancel(Quote $quote): Quote
    {
        if (! in_array($quote->status, [QuoteStatus::DRAFT, QuoteStatus::SENT], true)) {
            throw new \InvalidArgumentException('Ce devis ne peut plus être annulé.');
        }

        $quote->update(['status' => QuoteStatus::CANCELLED]);

        return $quote->fresh();
    }

    public function duplicate(Quote $quote): Quote
    {
        $originalLines = $quote->lines()->with('product', 'taxRate')->get();

        $newData = [
            'company_id' => $quote->company_id,
            'customer_id' => $quote->customer_id,
            'quote_date' => now()->toDateString(),
            'valid_until' => $quote->valid_until,
            'currency' => $quote->currency,
            'payment_terms_days' => $quote->payment_terms_days,
            'notes' => $quote->notes,
            'terms' => $quote->terms,
            'created_by' => $quote->created_by,
            'lines' => $originalLines->map(fn (QuoteLine $line) => [
                'product_id' => $line->product_id,
                'description' => $line->description,
                'quantity' => (string) $line->quantity,
                'unit' => $line->unit,
                'unit_price' => (string) $line->unit_price,
                'discount_percent' => (string) $line->discount_percent,
                'tax_rate_id' => $line->tax_rate_id,
            ])->toArray(),
        ];

        return $this->createDraft($newData);
    }

    public function deleteDraft(Quote $quote): void
    {
        if ($quote->status !== QuoteStatus::DRAFT) {
            throw new \InvalidArgumentException('Seul un devis en brouillon peut être supprimé.');
        }

        DB::transaction(function () use ($quote) {
            $quote->lines()->delete();
            $quote->delete();
        });
    }

    /**
     * Generate a unique quote number for the company.
     *
     * Format: DEV-YYYY-NNNNNN
     * Sequence resets each year.
     *
     * @return string Example: DEV-2026-000001
     */
    public function generateQuoteNumber(int $companyId): string
    {
        $year = (int) now()->year;
        $prefix = "DEV-{$year}-";
        $maxTries = 10;
        $maxNumber = Quote::where('company_id', $companyId)
            ->where('quote_number', 'like', $prefix.'%')
            ->pluck('quote_number')
            ->map(fn (string $num) => (int) substr($num, strlen($prefix)))
            ->filter(fn (int $num) => $num > 0)
            ->max() ?? 0;

        for ($attempt = 0; $attempt < $maxTries; $attempt++) {
            $maxNumber++;
            $candidate = $prefix.str_pad((string) $maxNumber, 6, '0', STR_PAD_LEFT);
            if (! Quote::where('company_id', $companyId)->where('quote_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Impossible de générer un numéro de devis unique après '.$maxTries.' tentatives.');
    }

    /**
     * Calculate a single line's financial values.
     *
     * @return array{quantity: string, unit_price: string, discount_percent: string, discount_amount: string, tax_amount: string, line_subtotal: string, line_total: string}
     */
    public function calculateLine(string $quantity, string $unitPrice, string $discountPercent, ?TaxRate $taxRate): array
    {
        bcscale(3);

        $qty = $this->toDecimal($quantity);
        $price = $this->toDecimal($unitPrice);
        $discountPct = $this->toDecimal($discountPercent);

        $gross = bcmul($qty, $price, 3);
        $discountAmount = bcdiv(bcmul($gross, $discountPct, 3), '100', 3);
        $lineSubtotal = bcsub($gross, $discountAmount, 3);

        $taxRateValue = $taxRate ? $this->toDecimal((string) $taxRate->rate) : '0';
        $taxAmount = bcdiv(bcmul($lineSubtotal, $taxRateValue, 3), '100', 3);
        $lineTotal = bcadd($lineSubtotal, $taxAmount, 3);

        return [
            'quantity' => $qty,
            'unit_price' => $price,
            'discount_percent' => $discountPct,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'line_subtotal' => $lineSubtotal,
            'line_total' => $lineTotal,
        ];
    }

    public function calculateTotals(Quote $quote): Quote
    {
        $lines = $quote->lines()->get();

        $subtotal = '0';
        $discountTotal = '0';
        $taxTotal = '0';
        $total = '0';

        foreach ($lines as $line) {
            $subtotal = bcadd($subtotal, (string) $line->line_subtotal, 3);
            $discountTotal = bcadd($discountTotal, (string) $line->discount_amount, 3);
            $taxTotal = bcadd($taxTotal, (string) $line->tax_amount, 3);
            $total = bcadd($total, (string) $line->line_total, 3);
        }

        $quote->update([
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'total' => $total,
        ]);

        return $quote->fresh();
    }

    public function validateCustomer(int $customerId, int $companyId): void
    {
        $customer = Customer::where('id', $customerId)
            ->where('company_id', $companyId)
            ->first();

        if (! $customer) {
            throw new \InvalidArgumentException('Le client sélectionné n\'appartient pas à cette société.');
        }

        if (! $customer->is_active) {
            throw new \InvalidArgumentException('Le client sélectionné est inactif. Veuillez choisir un client actif.');
        }
    }

    public function validateProduct(int $productId, int $companyId): void
    {
        $product = Product::where('id', $productId)
            ->where('company_id', $companyId)
            ->first();

        if (! $product) {
            throw new \InvalidArgumentException('Le produit/service sélectionné n\'appartient pas à cette société.');
        }

        if (! $product->is_active) {
            throw new \InvalidArgumentException('Le produit/service sélectionné est inactif.');
        }

        if (! $product->is_sellable) {
            throw new \InvalidArgumentException('Le produit/service sélectionné n\'est pas vendable.');
        }
    }

    public function validateTaxRate(int $taxRateId, int $companyId): void
    {
        $taxRate = TaxRate::where('id', $taxRateId)
            ->where('company_id', $companyId)
            ->first();

        if (! $taxRate) {
            throw new \InvalidArgumentException('Le taux de TVA sélectionné n\'appartient pas à cette société.');
        }

        if (! $taxRate->is_active) {
            throw new \InvalidArgumentException('Le taux de TVA sélectionné est inactif.');
        }
    }

    public function validateDates(string $quoteDate, ?string $validUntil): void
    {
        if ($validUntil !== null && $validUntil !== '' && $validUntil < $quoteDate) {
            throw new \InvalidArgumentException('La date de validité ne peut pas être antérieure à la date du devis.');
        }
    }

    /**
     * @param  array<int, array{product_id: int, description: string, quantity: string, unit: string, unit_price: string, discount_percent: string, tax_rate_id?: int|null}>  $linesData
     */
    public function validateLines(array $linesData): void
    {
        foreach ($linesData as $lineData) {
            $qty = $this->toDecimal($lineData['quantity']);
            $price = $this->toDecimal($lineData['unit_price']);
            $discount = $this->toDecimal($lineData['discount_percent']);

            if (bccomp($qty, '0', 3) <= 0) {
                throw new \InvalidArgumentException('La quantité doit être supérieure à zéro.');
            }

            if (bccomp($price, '0', 3) < 0) {
                throw new \InvalidArgumentException('Le prix unitaire ne peut pas être négatif.');
            }

            if (bccomp($discount, '0', 3) < 0 || bccomp($discount, '100', 3) > 0) {
                throw new \InvalidArgumentException('Le pourcentage de remise doit être compris entre 0 et 100.');
            }
        }
    }

    /**
     * @param  array<int, array{product_id: int, description: string, quantity: string, unit: string, unit_price: string, discount_percent: string, tax_rate_id?: int|null}>  $linesData
     */
    private function syncLines(Quote $quote, array $linesData, int $companyId): void
    {
        $quote->lines()->delete();

        foreach ($linesData as $index => $lineData) {
            $this->validateProduct((int) $lineData['product_id'], $companyId);

            $taxRate = null;
            if (! empty($lineData['tax_rate_id'])) {
                $this->validateTaxRate((int) $lineData['tax_rate_id'], $companyId);
                $taxRate = TaxRate::find($lineData['tax_rate_id']);
            }

            $calculated = $this->calculateLine(
                $lineData['quantity'],
                $lineData['unit_price'],
                $lineData['discount_percent'],
                $taxRate
            );

            QuoteLine::create([
                'quote_id' => $quote->id,
                'product_id' => $lineData['product_id'],
                'description' => $lineData['description'],
                'quantity' => $calculated['quantity'],
                'unit' => $lineData['unit'],
                'unit_price' => $calculated['unit_price'],
                'discount_percent' => $calculated['discount_percent'],
                'discount_amount' => $calculated['discount_amount'],
                'tax_rate_id' => $lineData['tax_rate_id'] ?? null,
                'tax_amount' => $calculated['tax_amount'],
                'line_subtotal' => $calculated['line_subtotal'],
                'line_total' => $calculated['line_total'],
                'sort_order' => $index + 1,
            ]);
        }
    }

    /**
     * Convert a string to a decimal with 3 decimal places.
     *
     * @return numeric-string
     */
    private function toDecimal(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '0';
        }

        return number_format((float) $normalized, 3, '.', '');
    }
}
