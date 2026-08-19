<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Show extends Component
{
    public ?Customer $customer = null;

    public function mount(int $customerId, CurrentCompany $currentCompany): void
    {
        $customer = Customer::findOrFail($customerId);

        $company = $currentCompany->get(Auth::user());

        if (! $company || $customer->company_id !== $company->id) {
            abort(403);
        }

        if (Auth::user()->cannot('view', $customer)) {
            abort(403);
        }

        $this->customer = $customer;
    }

    public function render()
    {
        return view('livewire.customers.show', [
            'customer' => $this->customer,
        ]);
    }
}
