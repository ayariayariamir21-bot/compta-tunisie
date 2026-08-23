<?php

namespace App\Livewire\Expenses;

use App\Models\Account;
use App\Models\CompanyAccountingSetting;
use App\Models\Expense;
use App\Models\Journal;
use App\Models\PaymentMethod;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Services\CurrentAccountingPeriod;
use App\Services\CurrentCompany;
use App\Services\CurrentFiscalYear;
use App\Services\ExpenseService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read array{subtotal: numeric-string, discount_total: numeric-string, tax_total: numeric-string, total: numeric-string} $totals
 */
#[Layout('layouts.app')]
#[Title('Nouvelle dépense')]
class Create extends Component
{
    public ?int $supplier_id = null;

    public ?int $payment_method_id = null;

    public ?int $journal_id = null;

    public string $expense_date = '';

    public string $due_date = '';

    public ?string $reference = null;

    public ?string $description = null;

    public ?string $notes = null;

    public bool $saveAndPost = false;

    /**
     * Editable expense lines.
     *
     * @var array<int, array{expense_account_id: ?int, label: string, quantity: string, unit_price: string, discount_percent: string, tax_rate_id: ?int}>
     */
    public array $lines = [];

    public function mount(): void
    {
        $this->expense_date = now()->toDateString();
        $this->lines = [$this->emptyLine()];

        $company = app(CurrentCompany::class)->get(Auth::user());

        if ($company) {
            $settings = CompanyAccountingSetting::where('company_id', $company->id)->first();

            if ($settings && $settings->default_misc_journal_id) {
                $journal = Journal::where('id', $settings->default_misc_journal_id)
                    ->where('company_id', $company->id)
                    ->where('is_active', true)
                    ->first();

                if ($journal) {
                    $this->journal_id = $journal->id;
                }
            }
        }
    }

    /**
     * @return array{expense_account_id: ?int, label: string, quantity: string, unit_price: string, discount_percent: string, tax_rate_id: ?int}
     */
    private function emptyLine(): array
    {
        return [
            'expense_account_id' => null,
            'label' => '',
            'quantity' => '',
            'unit_price' => '',
            'discount_percent' => '0',
            'tax_rate_id' => null,
        ];
    }

    public function addLine(): void
    {
        $this->lines[] = $this->emptyLine();
    }

    public function removeLine(int $index): void
    {
        if (count($this->lines) > 1) {
            unset($this->lines[$index]);
            $this->lines = array_values($this->lines);
        }
    }

    /** @return array{subtotal: numeric-string, discount_total: numeric-string, tax_total: numeric-string, total: numeric-string} */
    public function getTotalsProperty(): array
    {
        return $this->computeTotals($this->lines);
    }

    /**
     * @param  array<int, array{expense_account_id: int|null, label: string, quantity: string, unit_price: string, discount_percent: string, tax_rate_id: int|null}>  $rows
     * @return array{subtotal: numeric-string, discount_total: numeric-string, tax_total: numeric-string, total: numeric-string}
     */
    protected function computeTotals(array $rows): array
    {
        $service = app(ExpenseService::class);

        $subtotal = '0';
        $discountTotal = '0';
        $taxTotal = '0';
        $total = '0';

        foreach ($rows as $row) {
            if ($row['unit_price'] === '' && $row['label'] === '') {
                continue;
            }

            $taxRate = $row['tax_rate_id'] !== null ? TaxRate::find((int) $row['tax_rate_id']) : null;
            $calculated = $service->calculateLine($row['quantity'], $row['unit_price'], $row['discount_percent'], $taxRate);

            $discountTotal = bcadd($discountTotal, $calculated['discount_amount'], 3);
            $subtotal = bcadd($subtotal, $calculated['line_subtotal'], 3);
            $taxTotal = bcadd($taxTotal, $calculated['tax_amount'], 3);
            $total = bcadd($total, $calculated['line_total'], 3);
        }

        return [
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'total' => $total,
        ];
    }

    /**
     * @return array<int, array{expense_account_id: int, label: string, quantity?: string|null, unit_price: string, discount_percent: string, tax_rate_id?: int|null}>
     */
    protected function buildLinesData(): array
    {
        $data = [];

        foreach ($this->lines as $row) {
            $data[] = [
                'expense_account_id' => (int) $row['expense_account_id'],
                'label' => trim($row['label']),
                'quantity' => trim($row['quantity']),
                'unit_price' => $row['unit_price'],
                'discount_percent' => $row['discount_percent'],
                'tax_rate_id' => ! empty($row['tax_rate_id']) ? (int) $row['tax_rate_id'] : null,
            ];
        }

        return $data;
    }

    public function save(
        bool $withPost,
        ExpenseService $service,
        CurrentCompany $currentCompany,
        CurrentFiscalYear $currentFiscalYear,
        CurrentAccountingPeriod $currentAccountingPeriod,
    ): void {
        $this->saveAndPost = $withPost;

        $company = $currentCompany->get(Auth::user());
        $fiscalYear = $currentFiscalYear->get(Auth::user());
        $accountingPeriod = $currentAccountingPeriod->get(Auth::user());

        if (! $company || ! $fiscalYear || ! $accountingPeriod) {
            session()->flash('error', 'Contexte comptable incomplet. Veuillez sélectionner une société, un exercice et une période.');

            return;
        }

        if (Auth::user()->cannot('create', [Expense::class, $company])) {
            abort(403);
        }

        $this->validate([
            'supplier_id' => ['nullable', 'integer'],
            'payment_method_id' => ['nullable', 'integer'],
            'journal_id' => ['required', 'integer'],
            'expense_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.expense_account_id' => ['required', 'integer'],
            'lines.*.label' => ['required', 'string', 'max:500'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0.001'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'lines.*.tax_rate_id' => ['nullable', 'integer'],
        ]);

        try {
            $expense = $service->createDraft([
                'company_id' => $company->id,
                'supplier_id' => $this->supplier_id,
                'fiscal_year_id' => $fiscalYear->id,
                'accounting_period_id' => $accountingPeriod->id,
                'journal_id' => (int) $this->journal_id,
                'payment_method_id' => $this->payment_method_id,
                'expense_date' => $this->expense_date,
                'due_date' => $this->due_date !== '' ? $this->due_date : null,
                'reference' => $this->reference,
                'description' => $this->description,
                'notes' => $this->notes,
                'created_by' => (int) Auth::id(),
                'lines' => $this->buildLinesData(),
            ]);

            if ($this->saveAndPost) {
                $service->post($expense, (int) Auth::id());
            }
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $label = $this->saveAndPost ? 'créée et comptabilisée' : 'créée';
        session()->flash('success', "La dépense « {$expense->expense_number} » a été {$label}.");
        $this->redirect(route('expenses.show', $expense->id), navigate: true);
    }

    public function render(CurrentCompany $currentCompany): View
    {
        $company = $currentCompany->get(Auth::user());
        $fiscalYear = app(CurrentFiscalYear::class)->get(Auth::user());

        $suppliers = collect();
        $methods = collect();
        $journals = collect();
        $expenseAccounts = collect();
        $taxRates = collect();

        if ($company) {
            $suppliers = Supplier::where('company_id', $company->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            $methods = PaymentMethod::where('company_id', $company->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            $journalsQuery = Journal::where('company_id', $company->id)
                ->where('is_active', true)
                ->where('type', 'operations_diverses');

            $accountsQuery = Account::where('company_id', $company->id)
                ->where('is_active', true)
                ->where('account_type', 'expense');

            if ($fiscalYear) {
                $journalsQuery->where('fiscal_year_id', $fiscalYear->id);
                $accountsQuery->where('fiscal_year_id', $fiscalYear->id);
            }

            $journals = $journalsQuery->orderBy('code')->get();
            $expenseAccounts = $accountsQuery->orderBy('code')->get();

            $taxRates = TaxRate::where('company_id', $company->id)
                ->where('is_active', true)
                ->orderBy('rate')
                ->get();
        }

        return view('livewire.expenses.create', [
            'currentCompany' => $company,
            'suppliers' => $suppliers,
            'methods' => $methods,
            'journals' => $journals,
            'expenseAccounts' => $expenseAccounts,
            'taxRates' => $taxRates,
            'totals' => $this->totals,
        ]);
    }
}
