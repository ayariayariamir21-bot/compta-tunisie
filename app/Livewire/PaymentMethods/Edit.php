<?php

namespace App\Livewire\PaymentMethods;

use App\Enums\PaymentMethodType;
use App\Models\PaymentMethod;
use App\Services\Accounting\PaymentMethodService;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Edit extends Component
{
    public ?PaymentMethod $paymentMethod = null;

    public string $code = '';

    public string $name = '';

    public ?PaymentMethodType $type = null;

    public int $sort_order = 0;

    public ?string $description = null;

    public bool $is_active = true;

    public bool $is_default = false;

    public function mount(int $paymentMethodId, CurrentCompany $currentCompany): void
    {
        $paymentMethod = PaymentMethod::findOrFail($paymentMethodId);

        $company = $currentCompany->get(Auth::user());

        if (! $company) {
            abort(403);
        }

        if ($paymentMethod->company_id !== $company->id) {
            abort(403);
        }

        if (Auth::user()->cannot('update', $paymentMethod)) {
            abort(403);
        }

        $this->paymentMethod = $paymentMethod;
        $this->code = $paymentMethod->code;
        $this->name = $paymentMethod->name;
        $this->type = $paymentMethod->type;
        $this->sort_order = $paymentMethod->sort_order;
        $this->description = $paymentMethod->description;
        $this->is_active = $paymentMethod->is_active;
        $this->is_default = $paymentMethod->is_default;
    }

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

    public function update(CurrentCompany $currentCompany, PaymentMethodService $paymentMethodService): void
    {
        $company = $currentCompany->get(Auth::user());

        if (! $company) {
            session()->flash('error', 'Aucune société sélectionnée.');

            return;
        }

        if ($this->paymentMethod->company_id !== $company->id) {
            abort(403);
        }

        if (Auth::user()->cannot('update', $this->paymentMethod)) {
            abort(403);
        }

        $validated = $this->validate();

        try {
            $paymentMethodService->updatePaymentMethod($this->paymentMethod, $validated);
        } catch (\InvalidArgumentException $e) {
            $this->addError('code', $e->getMessage());

            return;
        }

        session()->flash('success', "Le moyen de paiement « {$this->name} » a été mis à jour.");

        $this->redirect(route('payment-methods.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.payment-methods.edit', [
            'paymentMethodTypes' => PaymentMethodType::cases(),
        ]);
    }
}
