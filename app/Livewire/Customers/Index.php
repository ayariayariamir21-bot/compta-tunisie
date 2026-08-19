<?php

namespace App\Livewire\Customers;

use App\Enums\CustomerType;
use App\Models\Customer;
use App\Services\CurrentCompany;
use App\Services\CustomerService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Index extends Component
{
    public string $search = '';

    public string $filterType = '';

    public string $filterActive = '';

    public int $perPage = 15;

    public function delete(Customer $customer, CustomerService $customerService): void
    {
        if (Auth::user()->cannot('delete', $customer)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());

        if (! $currentCompany || $customer->company_id !== $currentCompany->id) {
            abort(403);
        }

        try {
            $customerService->delete($customer);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            $this->redirect(route('customers.index'), navigate: true);

            return;
        }

        session()->flash('success', "Le client « {$customer->name} » a été supprimé.");

        $this->redirect(route('customers.index'), navigate: true);
    }

    public function toggleActive(Customer $customer, CustomerService $customerService): void
    {
        if (Auth::user()->cannot('activate', $customer)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());

        if (! $currentCompany || $customer->company_id !== $currentCompany->id) {
            abort(403);
        }

        if ($customer->is_active) {
            $customerService->deactivate($customer);
            $label = 'désactivé';
        } else {
            $customerService->activate($customer);
            $label = 'activé';
        }

        session()->flash('success', "Le client « {$customer->name} » a été {$label}.");

        $this->redirect(route('customers.index'), navigate: true);
    }

    public function render(CurrentCompany $currentCompany)
    {
        $company = $currentCompany->get(Auth::user());

        $customers = collect();

        if ($company) {
            $query = Customer::where('company_id', $company->id);

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
                $query->where('customer_type', $this->filterType);
            }

            if ($this->filterActive !== '') {
                $query->where('is_active', $this->filterActive === '1');
            }

            $customers = $query->orderBy('code')->paginate($this->perPage);
        }

        return view('livewire.customers.index', [
            'customers' => $customers,
            'currentCompany' => $company,
            'customerTypes' => CustomerType::cases(),
        ]);
    }
}
