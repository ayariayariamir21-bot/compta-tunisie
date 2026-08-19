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

class Create extends Component
{
    public string $code = '';

    public string $name = '';

    public string $rate = '0.000';

    public ?TaxType $type = null;

    public int $sort_order = 0;

    public ?string $description = null;

    public bool $is_active = true;

    public bool $is_default = false;

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

    public function store(CurrentCompany $currentCompany, TaxRateService $taxRateService): void
    {
        $company = $currentCompany->get(Auth::user());

        if (! $company) {
            session()->flash('error', 'Aucune société sélectionnée.');

            return;
        }

        if (Auth::user()->cannot('create', [TaxRate::class, $company])) {
            abort(403);
        }

        $validated = $this->validate();

        try {
            $taxRateService->create($company, $validated);
        } catch (\InvalidArgumentException $e) {
            $this->addError('code', $e->getMessage());

            return;
        }

        session()->flash('success', "Le taux de taxe « {$this->name} » a été créé.");

        $this->redirect(route('tax-rates.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.tax-rates.create', [
            'taxTypes' => TaxType::cases(),
        ]);
    }
}
