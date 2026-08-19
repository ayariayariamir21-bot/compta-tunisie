<?php

namespace App\Livewire\TaxRates;

use App\Enums\TaxType;
use App\Models\TaxRate;
use App\Services\Accounting\TaxRateService;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Index extends Component
{
    public string $search = '';

    public string $filterType = '';

    public string $filterActive = '';

    public function delete(TaxRate $taxRate, TaxRateService $taxRateService): void
    {
        if (Auth::user()->cannot('delete', $taxRate)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());

        if (! $currentCompany) {
            abort(403);
        }

        if ($taxRate->company_id !== $currentCompany->id) {
            abort(403);
        }

        try {
            $taxRateService->delete($taxRate);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            $this->redirect(route('tax-rates.index'), navigate: true);

            return;
        }

        session()->flash('success', "Le taux de taxe « {$taxRate->name} » a été supprimé.");

        $this->redirect(route('tax-rates.index'), navigate: true);
    }

    public function toggleActive(TaxRate $taxRate, TaxRateService $taxRateService): void
    {
        if (Auth::user()->cannot('activate', $taxRate)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());

        if (! $currentCompany) {
            abort(403);
        }

        if ($taxRate->company_id !== $currentCompany->id) {
            abort(403);
        }

        try {
            if ($taxRate->is_active) {
                $taxRateService->deactivate($taxRate);
                $label = 'désactivé';
            } else {
                $taxRateService->activate($taxRate);
                $label = 'activé';
            }
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            $this->redirect(route('tax-rates.index'), navigate: true);

            return;
        }

        session()->flash('success', "Le taux de taxe « {$taxRate->name} » a été {$label}.");

        $this->redirect(route('tax-rates.index'), navigate: true);
    }

    public function setDefault(TaxRate $taxRate, TaxRateService $taxRateService): void
    {
        if (Auth::user()->cannot('setDefault', $taxRate)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());

        if (! $currentCompany) {
            abort(403);
        }

        if ($taxRate->company_id !== $currentCompany->id) {
            abort(403);
        }

        $taxRateService->setDefault($taxRate);

        session()->flash('success', "Le taux de taxe « {$taxRate->name} » est maintenant par défaut.");

        $this->redirect(route('tax-rates.index'), navigate: true);
    }

    public function initializeDefaults(CurrentCompany $currentCompany, TaxRateService $taxRateService): void
    {
        $company = $currentCompany->get(Auth::user());

        if (! $company) {
            session()->flash('error', 'Aucune société sélectionnée.');

            return;
        }

        if (Auth::user()->cannot('create', [TaxRate::class, $company])) {
            abort(403);
        }

        $count = $taxRateService->initializeDefaults($company);

        if ($count > 0) {
            session()->flash('success', "{$count} taux de taxe initialisé(s) avec succès. Ces valeurs sont des exemples modifiables, pas des règles légales.");
        } else {
            session()->flash('success', 'Les taux de taxe sont déjà initialisés.');
        }

        $this->redirect(route('tax-rates.index'), navigate: true);
    }

    public function render(CurrentCompany $currentCompany): View
    {
        $company = $currentCompany->get(Auth::user());

        $taxRates = collect();

        if ($company) {
            $query = TaxRate::where('company_id', $company->id);

            if ($this->search !== '') {
                $search = $this->search;
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%");
                });
            }

            if ($this->filterType !== '') {
                $query->where('type', $this->filterType);
            }

            if ($this->filterActive !== '') {
                $query->where('is_active', $this->filterActive === '1');
            }

            $taxRates = $query->orderBy('sort_order')->orderBy('code')->get();
        }

        return view('livewire.tax-rates.index', [
            'taxRates' => $taxRates,
            'currentCompany' => $company,
            'taxTypes' => TaxType::cases(),
        ]);
    }
}
