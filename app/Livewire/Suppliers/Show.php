<?php

namespace App\Livewire\Suppliers;

use App\Models\Supplier;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Show extends Component
{
    public ?Supplier $supplier = null;

    public function mount(int $supplierId, CurrentCompany $currentCompany): void
    {
        $supplier = Supplier::findOrFail($supplierId);

        $company = $currentCompany->get(Auth::user());

        if (! $company || $supplier->company_id !== $company->id) {
            abort(403);
        }

        if (Auth::user()->cannot('view', $supplier)) {
            abort(403);
        }

        $this->supplier = $supplier;
    }

    public function render(): View
    {
        return view('livewire.suppliers.show', ['supplier' => $this->supplier]);
    }
}
