<?php

namespace App\Livewire\Reports;

use App\Models\Supplier;
use App\Services\Accounting\SupplierStatementService;
use App\Services\CurrentCompany;
use App\Services\CurrentFiscalYear;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Relevé fournisseur')]
class SupplierStatement extends Component
{
    public ?int $supplierId = null;

    #[Validate(['nullable', 'date'])]
    public string $fromDate = '';

    #[Validate(['nullable', 'date', 'after_or_equal:fromDate'])]
    public string $toDate = '';

    /**
     * Defaults follow the existing reporting convention (General Ledger,
     * Customer Statement): from_date = current fiscal year start,
     * to_date = current fiscal year end.
     */
    public function mount(?int $supplierId = null): void
    {
        $this->supplierId = $supplierId;

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
            'purchase_invoice' => 'Facture fournisseur',
            'supplier_payment' => 'Règlement fournisseur',
            default => ucfirst($type),
        };
    }

    public function render(
        SupplierStatementService $supplierStatementService,
        CurrentCompany $currentCompany,
        CurrentFiscalYear $currentFiscalYear,
    ): View {
        $company = $currentCompany->get(Auth::user());
        $fiscalYear = $currentFiscalYear->get(Auth::user());

        $suppliers = $company !== null
            ? $supplierStatementService->getSuppliersForContext($company)
            : collect();

        $supplier = null;
        $statement = null;

        // URL/Livewire state provided supplier is revalidated against CurrentCompany.
        if ($this->supplierId !== null && $company !== null) {
            /** @var Supplier|null $supplier */
            $supplier = Supplier::where('id', $this->supplierId)
                ->where('company_id', $company->id)
                ->first();

            if ($supplier === null) {
                abort(404);
            }
        }

        if ($supplier !== null) {
            try {
                $statement = $supplierStatementService->getStatement(
                    $supplier,
                    $this->fromDate !== '' ? $this->fromDate : null,
                    $this->toDate !== '' ? $this->toDate : null,
                );
            } catch (\InvalidArgumentException) {
                // Inverted or invalid period: the view shows the placeholder.
                $statement = null;
            }
        }

        return view('livewire.reports.supplier-statement', [
            'currentCompany' => $company,
            'currentFiscalYear' => $fiscalYear,
            'suppliers' => $suppliers,
            'supplier' => $supplier,
            'statement' => $statement,
        ]);
    }
}
