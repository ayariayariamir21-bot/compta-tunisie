<?php

namespace App\Livewire\Companies;

use App\Models\Company;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Index extends Component
{
    public function deactivate(Company $company, CurrentCompany $currentCompany): void
    {
        if (Auth::user()->cannot('update', $company)) {
            abort(403);
        }

        $company->update(['is_active' => false]);

        $company->users()->updateExistingPivot(
            Auth::id(),
            ['is_active' => false]
        );

        $current = $currentCompany->get(Auth::user());

        if ($current && $current->id === $company->id) {
            $currentCompany->clear();
        }

        session()->flash('success', 'La société a été désactivée.');

        $this->redirect(route('companies.index'), navigate: true);
    }

    public function activate(Company $company): void
    {
        if (Auth::user()->cannot('update', $company)) {
            abort(403);
        }

        $company->update(['is_active' => true]);

        $company->users()->updateExistingPivot(
            Auth::id(),
            ['is_active' => true]
        );

        session()->flash('success', 'La société a été activée.');

        $this->redirect(route('companies.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.companies.index', [
            'companies' => Auth::user()
                ->companies()
                ->orderBy('name')
                ->get(),
        ]);
    }
}
