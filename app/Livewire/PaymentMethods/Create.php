<?php

namespace App\Livewire\PaymentMethods;

use App\Enums\PaymentMethodType;
use App\Models\PaymentMethod;
use App\Services\Accounting\PaymentMethodService;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Create extends Component
{
    public string $code = '';

    public string $name = '';

    public ?PaymentMethodType $type = null;

    public int $sort_order = 0;

    public ?string $description = null;

    public bool $is_active = true;

    public bool $is_default = false;

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'enum:'.PaymentMethodType::class],
            'sort_order' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'code' => 'le code',
            'name' => 'le nom',
            'type' => 'le type',
            'sort_order' => 'l\'ordre d\'affichage',
            'description' => 'la description',
            'is_active' => 'l\'état',
            'is_default' => 'le statut par défaut',
        ];
    }

    public function store(CurrentCompany $currentCompany, PaymentMethodService $paymentMethodService): void
    {
        $company = $currentCompany->get(Auth::user());

        if (! $company) {
            session()->flash('error', 'Aucune société sélectionnée.');

            return;
        }

        if (Auth::user()->cannot('create', [PaymentMethod::class, $company])) {
            abort(403);
        }

        $validated = $this->validate();

        try {
            $paymentMethodService->createPaymentMethod($company, $validated);
        } catch (\InvalidArgumentException $e) {
            $this->addError('code', $e->getMessage());

            return;
        }

        session()->flash('success', "Le moyen de paiement « {$this->name} » a été créé.");

        $this->redirect(route('payment-methods.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.payment-methods.create', [
            'paymentMethodTypes' => PaymentMethodType::cases(),
        ]);
    }
}
