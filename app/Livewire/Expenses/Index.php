<?php

namespace App\Livewire\Expenses;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\PaymentMethod;
use App\Models\Supplier;
use App\Services\CurrentCompany;
use App\Services\ExpenseService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Dépenses')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterStatus = '';

    public ?int $filterSupplierId = null;

    public ?int $filterPaymentMethodId = null;

    public string $filterDateFrom = '';

    public string $filterDateTo = '';

    public int $perPage = 15;

    public function delete(Expense $expense): void
    {
        if (Auth::user()->cannot('delete', $expense)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        if (! $currentCompany || $expense->company_id !== $currentCompany->id) {
            abort(403);
        }

        try {
            app(ExpenseService::class)->deleteDraft($expense);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
            $this->redirect(route('expenses.index'), navigate: true);

            return;
        }

        session()->flash('success', "La dépense « {$expense->expense_number} » a été supprimée.");
        $this->redirect(route('expenses.index'), navigate: true);
    }

    public function post(ExpenseService $service, Expense $expense): void
    {
        if (Auth::user()->cannot('post', $expense)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        if (! $currentCompany || $expense->company_id !== $currentCompany->id) {
            abort(403);
        }

        try {
            $service->post($expense, (int) Auth::id());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
            $this->redirect(route('expenses.index'), navigate: true);

            return;
        }

        session()->flash('success', "La dépense « {$expense->expense_number} » a été comptabilisée.");
        $this->redirect(route('expenses.index'), navigate: true);
    }

    public function cancel(Expense $expense): void
    {
        if (Auth::user()->cannot('cancel', $expense)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        if (! $currentCompany || $expense->company_id !== $currentCompany->id) {
            abort(403);
        }

        try {
            app(ExpenseService::class)->cancel($expense);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
            $this->redirect(route('expenses.index'), navigate: true);

            return;
        }

        session()->flash('success', "La dépense « {$expense->expense_number} » a été annulée.");
        $this->redirect(route('expenses.index'), navigate: true);
    }

    public function render(CurrentCompany $currentCompany): View
    {
        $company = $currentCompany->get(Auth::user());
        $expenses = collect();
        $suppliers = collect();
        $paymentMethods = collect();

        if ($company) {
            $query = Expense::where('expenses.company_id', $company->id)
                ->with(['supplier', 'paymentMethod']);

            if ($this->search !== '') {
                $search = $this->search;
                $query->where(function ($q) use ($search) {
                    $q->where('expense_number', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            }

            if ($this->filterStatus !== '') {
                $query->where('status', $this->filterStatus);
            }

            if ($this->filterSupplierId !== null) {
                $query->where('supplier_id', $this->filterSupplierId);
            }

            if ($this->filterPaymentMethodId !== null) {
                $query->where('payment_method_id', $this->filterPaymentMethodId);
            }

            if ($this->filterDateFrom !== '') {
                $query->where('expense_date', '>=', $this->filterDateFrom);
            }

            if ($this->filterDateTo !== '') {
                $query->where('expense_date', '<=', $this->filterDateTo);
            }

            $expenses = $query->orderByDesc('expense_date')
                ->orderByDesc('id')
                ->paginate($this->perPage);

            $suppliers = Supplier::where('company_id', $company->id)
                ->orderBy('name')
                ->get();

            $paymentMethods = PaymentMethod::where('company_id', $company->id)
                ->orderBy('sort_order')
                ->get();
        }

        return view('livewire.expenses.index', [
            'expenses' => $expenses,
            'currentCompany' => $company,
            'statuses' => ExpenseStatus::cases(),
            'suppliers' => $suppliers,
            'paymentMethods' => $paymentMethods,
        ]);
    }
}
