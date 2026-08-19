<?php

namespace App\Livewire\TaxRates;

use App\Enums\TaxType;
use App\Models\TaxRate;
use App\Services\Accounting\TaxRateService;
use App\Services\CurrentCompany;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Edit extends Component
{
    public ?TaxRate $taxRate = null;

    public string $code = '';

    public string $name = '';

    public string $rate = '0.000';

    public ?string $type = null;

    public int $sort_order = 0;

    public ?string $description = null;

    public bool $is_active = true;

    public bool $is_default = false;

    public function mount(int $taxRateId, CurrentCompany $currentCompany): void
    {
        $taxRate = TaxRate::findOrFail($taxRateId);

        $company = $currentCompany->get(Auth::user());

        if (! $company) {
            abort(403);
        }

        if ($taxRate->company_id !== $company->id) {
            abort(403);
        }

        if (Auth::user()->cannot('update', $taxRate)) {
            abort(403);
        }

        $this->taxRate = $taxRate;
        $this->code = $taxRate->code;
        $this->name = $taxRate->name;
        $this->rate = (string) $taxRate->rate;
        $this->type = $taxRate->type;
        $this->sort_order = $taxRate->sort_order;
        $this->description = $taxRate->description;
        $this->is_active = $taxRate->is_active;
        $this->is_default = $taxRate->is_default;
    }

    /** @return array<string, list<string|ValidationRule>> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'type' => ['required', 'enum:'.TaxType::class],
            'sort_order' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function validationAttributes(): array
    {
        return [
            'code' => 'le code',
            'name' => 'le nom',
            'rate' => 'le taux',
            'type' => 'le type',
            'sort_order' => 'l\'ordre d\'affichage',
            'description' => 'la description',
            'is_active' => 'l\'état',
            'is_default' => 'le statut par défaut',
        ];
    }

    public function update(CurrentCompany $currentCompany, TaxRateService $taxRateService): void
    {
        $company = $currentCompany->get(Auth::user());

        if (! $company) {
            session()->flash('error', 'Aucune société sélectionnée.');

            return;
        }

        if ($this->taxRate->company_id !== $company->id) {
            abort(403);
        }

        if (Auth::user()->cannot('update', $this->taxRate)) {
            abort(403);
        }

        $validated = $this->validate();

        try {
            $taxRateService->update($this->taxRate, $validated);
        } catch (\InvalidArgumentException $e) {
            $this->addError('code', $e->getMessage());

            return;
        }

        session()->flash('success', "Le taux de taxe « {$this->name} » a été mis à jour.");

        $this->redirect(route('tax-rates.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.tax-rates.edit', [
            'taxTypes' => TaxType::cases(),
        ]);
    }
}
