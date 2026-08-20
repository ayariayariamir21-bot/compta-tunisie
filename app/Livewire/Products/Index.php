<?php

namespace App\Livewire\Products;

use App\Enums\ProductType;
use App\Models\Product;
use App\Services\CurrentCompany;
use App\Services\ProductService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Index extends Component
{
    public string $search = '';

    public string $filterType = '';

    public string $filterActive = '';

    public string $filterSellable = '';

    public string $filterPurchasable = '';

    public int $perPage = 15;

    public function delete(Product $product, ProductService $productService): void
    {
        if (Auth::user()->cannot('delete', $product)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        if (! $currentCompany || $product->company_id !== $currentCompany->id) {
            abort(403);
        }

        try {
            $productService->delete($product);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
            $this->redirect(route('products.index'), navigate: true);

            return;
        }

        session()->flash('success', "Le produit/service « {$product->name} » a été supprimé.");
        $this->redirect(route('products.index'), navigate: true);
    }

    public function toggleActive(Product $product, ProductService $productService): void
    {
        if (Auth::user()->cannot('activate', $product)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        if (! $currentCompany || $product->company_id !== $currentCompany->id) {
            abort(403);
        }

        if ($product->is_active) {
            $productService->deactivate($product);
            $label = 'désactivé';
        } else {
            $productService->activate($product);
            $label = 'activé';
        }

        session()->flash('success', "Le produit/service « {$product->name} » a été {$label}.");
        $this->redirect(route('products.index'), navigate: true);
    }

    public function render(CurrentCompany $currentCompany): View
    {
        $company = $currentCompany->get(Auth::user());
        $products = collect();

        if ($company) {
            $query = Product::where('company_id', $company->id);

            if ($this->search !== '') {
                $search = $this->search;
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            if ($this->filterType !== '') {
                $query->where('type', $this->filterType);
            }

            if ($this->filterActive !== '') {
                $query->where('is_active', $this->filterActive === '1');
            }

            if ($this->filterSellable !== '') {
                $query->where('is_sellable', $this->filterSellable === '1');
            }

            if ($this->filterPurchasable !== '') {
                $query->where('is_purchasable', $this->filterPurchasable === '1');
            }

            $products = $query->orderBy('code')->paginate($this->perPage);
        }

        return view('livewire.products.index', [
            'products' => $products,
            'currentCompany' => $company,
            'productTypes' => ProductType::cases(),
        ]);
    }
}
