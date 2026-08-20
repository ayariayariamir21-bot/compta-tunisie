<?php

namespace App\Livewire\Suppliers;

use App\Enums\SupplierType;
use App\Models\Supplier;
use App\Services\CurrentCompany;
use App\Services\SupplierService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Index extends Component
{
    public string $search = '';

    public string $filterType = '';

    public string $filterActive = '';

    public int $perPage = 15;

    public function delete(Supplier $supplier, SupplierService $supplierService): void
    {
        if (Auth::user()->cannot('delete', $supplier)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        if (! $currentCompany || $supplier->company_id !== $currentCompany->id) {
            abort(403);
        }

        try {
            $supplierService->delete($supplier);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
            $this->redirect(route('suppliers.index'), navigate: true);

            return;
        }

        session()->flash('success', "Le fournisseur « {$supplier->name} » a été supprimé.");
        $this->redirect(route('suppliers.index'), navigate: true);
    }

    public function toggleActive(Supplier $supplier, SupplierService $supplierService): void
    {
        if (Auth::user()->cannot('activate', $supplier)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        if (! $currentCompany || $supplier->company_id !== $currentCompany->id) {
            abort(403);
        }

        if ($supplier->is_active) {
            $supplierService->deactivate($supplier);
            $label = 'désactivé';
        } else {
            $supplierService->activate($supplier);
            $label = 'activé';
        }

        session()->flash('success', "Le fournisseur « {$supplier->name} » a été {$label}.");
        $this->redirect(route('suppliers.index'), navigate: true);
    }

    public function render(CurrentCompany $currentCompany): View
    {
        $company = $currentCompany->get(Auth::user());
        $suppliers = collect();

        if ($company) {
            $query = Supplier::where('company_id', $company->id);

            if ($this->search !== '') {
                $search = $this->search;
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('legal_name', 'like', "%{$search}%")
                        ->orWhere('tax_identifier', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            if ($this->filterType !== '') {
                $query->where('supplier_type', $this->filterType);
            }

            if ($this->filterActive !== '') {
                $query->where('is_active', $this->filterActive === '1');
            }

            $suppliers = $query->orderBy('code')->paginate($this->perPage);
        }

        return view('livewire.suppliers.index', [
            'suppliers' => $suppliers,
            'currentCompany' => $company,
            'supplierTypes' => SupplierType::cases(),
        ]);
    }
}
