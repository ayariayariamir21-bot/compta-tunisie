<?php

namespace App\Livewire\Products;

use App\Models\Product;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Show extends Component
{
    public ?Product $product = null;

    public function mount(int $productId, CurrentCompany $currentCompany): void
    {
        $product = Product::findOrFail($productId);

        $company = $currentCompany->get(Auth::user());

        if (! $company || $product->company_id !== $company->id) {
            abort(403);
        }

        if (Auth::user()->cannot('view', $product)) {
            abort(403);
        }

        $this->product = $product;
    }

    public function render(): View
    {
        return view('livewire.products.show', ['product' => $this->product]);
    }
}
