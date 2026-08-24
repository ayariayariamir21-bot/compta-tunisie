<?php

namespace App\Livewire\Companies;

use App\Enums\AuditAction;
use App\Models\Company;
use App\Services\Security\AuditLogService as SecurityAuditLogService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Create extends Component
{
    public string $name = '';

    public ?string $legal_name = null;

    public ?string $tax_identifier = null;

    public ?string $registration_number = null;

    public ?string $legal_form = null;

    public ?string $address = null;

    public ?string $city = null;

    public ?string $phone = null;

    public ?string $email = null;

    public string $currency = 'TND';

    public string $country = 'TN';

    /** @return array<string, list<string|ValidationRule>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_identifier' => ['nullable', 'string', 'max:50'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'legal_form' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'country' => ['required', 'string', 'size:2'],
        ];
    }

    /** @return array<string, string> */
    public function validationAttributes(): array
    {
        return [
            'name' => 'le nom',
            'legal_name' => 'la raison sociale',
            'tax_identifier' => "l'identifiant fiscal",
            'registration_number' => "le numéro d'immatriculation",
            'legal_form' => 'la forme juridique',
            'address' => "l'adresse",
            'city' => 'la ville',
            'phone' => 'le téléphone',
            'email' => "l'email",
            'currency' => 'la devise',
            'country' => 'le pays',
        ];
    }

    public function store(): void
    {
        $validated = $this->validate();

        $company = Company::create([
            ...$validated,
            'is_active' => true,
        ]);

        Auth::user()->companies()->attach($company->id, [
            'role' => 'admin',
            'is_active' => true,
        ]);

        app(SecurityAuditLogService::class)->logAction(
            AuditAction::CompanyCreated,
            "Société créée : {$company->name}.",
            company: $company,
            entity: $company,
        );

        session()->flash('success', 'La société a été créée avec succès.');

        $this->redirect(route('companies.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.companies.create');
    }
}
