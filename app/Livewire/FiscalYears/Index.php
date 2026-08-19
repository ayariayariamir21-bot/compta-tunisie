<?php

namespace App\Livewire\FiscalYears;

use App\Models\FiscalYear;
use App\Services\CurrentCompany;
use App\Services\CurrentFiscalYear;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Index extends Component
{
    public function activate(FiscalYear $fiscalYear, CurrentFiscalYear $currentFiscalYear): void
    {
        if (Auth::user()->cannot('activate', $fiscalYear)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());

        if (! $currentCompany || $fiscalYear->company_id !== $currentCompany->id) {
            abort(403);
        }

        FiscalYear::where('company_id', $currentCompany->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $fiscalYear->update(['is_active' => true]);

        $currentFiscalYear->set(Auth::user(), $fiscalYear->id);

        session()->flash('success', "L'exercice « {$fiscalYear->name} » est maintenant actif.");

        $this->redirect(route('fiscal-years.index'), navigate: true);
    }

    public function close(FiscalYear $fiscalYear): void
    {
        if (Auth::user()->cannot('close', $fiscalYear)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());

        if (! $currentCompany || $fiscalYear->company_id !== $currentCompany->id) {
            abort(403);
        }

        $fiscalYear->update(['is_closed' => true, 'is_active' => false]);

        $currentFiscalYear = app(CurrentFiscalYear::class);
        $current = $currentFiscalYear->get(Auth::user());

        if ($current && $current->id === $fiscalYear->id) {
            $currentFiscalYear->clear();
        }

        session()->flash('success', "L'exercice « {$fiscalYear->name} » a été clôturé.");

        $this->redirect(route('fiscal-years.index'), navigate: true);
    }

    public function render(CurrentCompany $currentCompany)
    {
        $company = $currentCompany->get(Auth::user());

        return view('livewire.fiscal-years.index', [
            'fiscalYears' => $company
                ? FiscalYear::where('company_id', $company->id)
                    ->orderByDesc('start_date')
                    ->get()
                : collect(),
            'currentCompany' => $company,
        ]);
    }
}
