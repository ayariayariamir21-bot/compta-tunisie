<?php

namespace App\Livewire\PaymentMethods;

use App\Enums\PaymentMethodType;
use App\Models\PaymentMethod;
use App\Services\Accounting\PaymentMethodService;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Index extends Component
{
    public string $search = '';

    public string $filterType = '';

    public string $filterActive = '';

    public function delete(PaymentMethod $paymentMethod, PaymentMethodService $paymentMethodService): void
    {
        if (Auth::user()->cannot('delete', $paymentMethod)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());

        if (! $currentCompany) {
            abort(403);
        }

        if ($paymentMethod->company_id !== $currentCompany->id) {
            abort(403);
        }

        try {
            $paymentMethodService->delete($paymentMethod);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            $this->redirect(route('payment-methods.index'), navigate: true);

            return;
        }

        session()->flash('success', "Le moyen de paiement « {$paymentMethod->name} » a été supprimé.");

        $this->redirect(route('payment-methods.index'), navigate: true);
    }

    public function toggleActive(PaymentMethod $paymentMethod, PaymentMethodService $paymentMethodService): void
    {
        if (Auth::user()->cannot('activate', $paymentMethod)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());

        if (! $currentCompany) {
            abort(403);
        }

        if ($paymentMethod->company_id !== $currentCompany->id) {
            abort(403);
        }

        try {
            if ($paymentMethod->is_active) {
                $paymentMethodService->deactivate($paymentMethod);
                $label = 'désactivé';
            } else {
                $paymentMethodService->activate($paymentMethod);
                $label = 'activé';
            }
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            $this->redirect(route('payment-methods.index'), navigate: true);

            return;
        }

        session()->flash('success', "Le moyen de paiement « {$paymentMethod->name} » a été {$label}.");

        $this->redirect(route('payment-methods.index'), navigate: true);
    }

    public function setDefault(PaymentMethod $paymentMethod, PaymentMethodService $paymentMethodService): void
    {
        if (Auth::user()->cannot('setDefault', $paymentMethod)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());

        if (! $currentCompany) {
            abort(403);
        }

        if ($paymentMethod->company_id !== $currentCompany->id) {
            abort(403);
        }

        $paymentMethodService->setDefault($paymentMethod);

        session()->flash('success', "Le moyen de paiement « {$paymentMethod->name} » est maintenant par défaut.");

        $this->redirect(route('payment-methods.index'), navigate: true);
    }

    public function initializeDefaults(CurrentCompany $currentCompany, PaymentMethodService $paymentMethodService): void
    {
        $company = $currentCompany->get(Auth::user());

        if (! $company) {
            session()->flash('error', 'Aucune société sélectionnée.');

            return;
        }

        if (Auth::user()->cannot('create', [PaymentMethod::class, $company])) {
            abort(403);
        }

        $count = $paymentMethodService->initializeDefaults($company);

        if ($count > 0) {
            session()->flash('success', "{$count} moyen(aux) de paiement initialisé(s) avec succès.");
        } else {
            session()->flash('success', 'Les moyens de paiement sont déjà initialisés.');
        }

        $this->redirect(route('payment-methods.index'), navigate: true);
    }

    public function render(CurrentCompany $currentCompany): View
    {
        $company = $currentCompany->get(Auth::user());

        $paymentMethods = collect();

        if ($company) {
            $query = PaymentMethod::where('company_id', $company->id);

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

            $paymentMethods = $query->orderBy('sort_order')->orderBy('code')->get();
        }

        return view('livewire.payment-methods.index', [
            'paymentMethods' => $paymentMethods,
            'currentCompany' => $company,
            'paymentMethodTypes' => PaymentMethodType::cases(),
        ]);
    }
}
