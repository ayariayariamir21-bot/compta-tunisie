<?php

namespace App\Livewire\Expenses;

use App\Models\Expense;
use App\Services\CurrentCompany;
use App\Services\ExpenseService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Détail de la dépense')]
class Show extends Component
{
    public int $expenseId;

    public function mount(int $expenseId): void
    {
        $this->expenseId = $expenseId;
    }

    public function delete(Expense $expense): void
    {
        if (Auth::user()->cannot('delete', $expense)) {
            abort(403);
        }

        $company = app(CurrentCompany::class)->get(Auth::user());
        if (! $company || $expense->company_id !== $company->id) {
            abort(403);
        }

        try {
            app(ExpenseService::class)->deleteDraft($expense);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

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

        $company = app(CurrentCompany::class)->get(Auth::user());
        if (! $company || $expense->company_id !== $company->id) {
            abort(403);
        }

        try {
            $service->post($expense, (int) Auth::id());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        session()->flash('success', "La dépense « {$expense->expense_number} » a été comptabilisée.");

        $this->redirect(route('expenses.show', $expense->id), navigate: true);
    }

    public function cancel(Expense $expense): void
    {
        if (Auth::user()->cannot('cancel', $expense)) {
            abort(403);
        }

        $company = app(CurrentCompany::class)->get(Auth::user());
        if (! $company || $expense->company_id !== $company->id) {
            abort(403);
        }

        try {
            app(ExpenseService::class)->cancel($expense);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        session()->flash('success', "La dépense « {$expense->expense_number} » a été annulée.");
        $this->redirect(route('expenses.show', $expense->id), navigate: true);
    }

    public function render(CurrentCompany $currentCompany): View
    {
        $company = $currentCompany->get(Auth::user());

        if (! $company) {
            abort(403);
        }

        /** @var Expense|null $expense */
        $expense = Expense::where('id', $this->expenseId)
            ->where('company_id', $company->id)
            ->with([
                'supplier', 'paymentMethod', 'journal', 'fiscalYear', 'accountingPeriod',
                'lines.expenseAccount', 'lines.taxRate',
                'journalEntry.lines.account', 'creator',
            ])
            ->first();

        if (! $expense) {
            abort(404);
        }

        return view('livewire.expenses.show', [
            'expense' => $expense,
            'currentCompany' => $company,
        ]);
    }
}
