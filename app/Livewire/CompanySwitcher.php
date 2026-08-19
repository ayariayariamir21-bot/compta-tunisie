<?php

namespace App\Livewire;

use App\Services\CurrentCompany;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class CompanySwitcher extends Component
{
    public ?int $companyId = null;

    public function mount(CurrentCompany $currentCompany): void
    {
        $company = $currentCompany->get(Auth::user());

        $this->companyId = $company?->id;
    }

    public function switchCompany(CurrentCompany $currentCompany): void
    {
        $this->validate([
            'companyId' => ['required', 'integer'],
        ]);

        $success = $currentCompany->set(
            Auth::user(),
            $this->companyId
        );

        if (! $success) {
            $this->addError(
                'companyId',
                'You do not have access to this company.'
            );

            return;
        }

        session()->forget('current_fiscal_year_id');
        session()->forget('current_accounting_period_id');

        $this->redirect(
            url()->previous(),
            navigate: true
        );
    }

    public function render(): View
    {
        return view('livewire.company-switcher', [
            'companies' => Auth::user()
                ->companies()
                ->wherePivot('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }
}
