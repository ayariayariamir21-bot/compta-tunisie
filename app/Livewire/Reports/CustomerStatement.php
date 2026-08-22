<?php

namespace App\Livewire\Reports;

use App\Models\Customer;
use App\Services\Accounting\CustomerStatementService;
use App\Services\CurrentCompany;
use App\Services\CurrentFiscalYear;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app')]
class CustomerStatement extends Component
{
    public ?int $customerId = null;

    #[Validate(['nullable', 'date'])]
    public string $fromDate = '';

    #[Validate(['nullable', 'date', 'after_or_equal:fromDate'])]
    public string $toDate = '';

    /**
     * Defaults follow the existing reporting convention (General Ledger):
     * from_date = current fiscal year start, to_date = current fiscal year end.
     */
    public function mount(?int $customerId = null): void
    {
        $this->customerId = $customerId;

        $fiscalYear = app(CurrentFiscalYear::class)->get(Auth::user());

        if ($fiscalYear) {
            $this->fromDate = Carbon::parse($fiscalYear->start_date)->format('Y-m-d');
            $this->toDate = Carbon::parse($fiscalYear->end_date)->format('Y-m-d');
        }
    }

    /**
     * French validation messages for the period filters.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fromDate.date' => 'La date de début est invalide.',
            'toDate.date' => 'La date de fin est invalide.',
            'toDate.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
        ];
    }

    /**
     * French display label for a statement row type.
     */
    public function typeLabel(string $type): string
    {
        return match ($type) {
            'invoice' => 'Facture',
            'credit_note' => 'Avoir',
            'payment' => 'Encaissement',
            default => ucfirst($type),
        };
    }

    public function render(
        CustomerStatementService $customerStatementService,
        CurrentCompany $currentCompany,
        CurrentFiscalYear $currentFiscalYear,
    ): View {
        $company = $currentCompany->get(Auth::user());
        $fiscalYear = $currentFiscalYear->get(Auth::user());

        $customers = $company !== null
            ? $customerStatementService->getCustomersForContext($company)
            : collect();

        $customer = null;
        $statement = null;

        // URL/Livewire state provided customer is revalidated against CurrentCompany.
        if ($this->customerId !== null && $company !== null) {
            /** @var Customer|null $customer */
            $customer = Customer::where('id', $this->customerId)
                ->where('company_id', $company->id)
                ->first();

            if ($customer === null) {
                abort(404);
            }
        }

        if ($customer !== null) {
            try {
                $statement = $customerStatementService->getStatement(
                    $customer,
                    $this->fromDate !== '' ? $this->fromDate : null,
                    $this->toDate !== '' ? $this->toDate : null,
                );
            } catch (\InvalidArgumentException) {
                // Inverted or invalid period: the view shows the placeholder.
                $statement = null;
            }
        }

        return view('livewire.reports.customer-statement', [
            'currentCompany' => $company,
            'currentFiscalYear' => $fiscalYear,
            'customers' => $customers,
            'customer' => $customer,
            'statement' => $statement,
        ]);
    }
}
