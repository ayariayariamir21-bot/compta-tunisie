<?php

namespace App\Livewire\FiscalYears;

use App\Enums\AuditAction;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Services\CurrentCompany;
use App\Services\Security\AuditLogService as SecurityAuditLogService;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;

class Create extends Component
{
    public string $name = '';

    public string $code = '';

    public string $start_date = '';

    public string $end_date = '';

    /** @return array<string, list<string|ValidationRule>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ];
    }

    /** @return array<string, string> */
    public function validationAttributes(): array
    {
        return [
            'name' => 'le nom',
            'code' => 'le code',
            'start_date' => 'la date de début',
            'end_date' => 'la date de fin',
        ];
    }

    public function store(CurrentCompany $currentCompany): void
    {
        $company = $currentCompany->get(Auth::user());

        if (! $company) {
            session()->flash('error', 'Aucune société sélectionnée.');

            return;
        }

        if (Auth::user()->cannot('create', [FiscalYear::class, $company])) {
            abort(403);
        }

        $validated = $this->validate();

        $hasOverlap = FiscalYear::where('company_id', $company->id)
            ->where('code', $validated['code'])
            ->exists();

        if ($hasOverlap) {
            $this->addError('code', 'Un exercice avec ce code existe déjà pour cette société.');

            return;
        }

        $dateOverlap = FiscalYear::where('company_id', $company->id)
            ->where('start_date', '<=', $validated['end_date'])
            ->where('end_date', '>=', $validated['start_date'])
            ->exists();

        if ($dateOverlap) {
            $this->addError('start_date', 'Cet exercice chevauche un exercice existant.');

            return;
        }

        $hasActive = FiscalYear::where('company_id', $company->id)
            ->where('is_active', true)
            ->exists();

        DB::transaction(function () use ($validated, $company, $hasActive) {
            $fiscalYear = FiscalYear::create([
                ...$validated,
                'company_id' => $company->id,
                'is_active' => ! $hasActive,
                'is_closed' => false,
            ]);

            $this->generateMonthlyPeriods($fiscalYear);

            app(SecurityAuditLogService::class)->logAction(
                AuditAction::FiscalYearCreated,
                "Exercice créé : {$fiscalYear->name}.",
                company: $company,
                entity: $fiscalYear,
            );
        });

        session()->flash('success', "L'exercice « {$validated['name']} » a été créé avec succès.");

        $this->redirect(route('fiscal-years.index'), navigate: true);
    }

    private function generateMonthlyPeriods(FiscalYear $fiscalYear): void
    {
        $start = Carbon::parse($fiscalYear->start_date);
        $end = Carbon::parse($fiscalYear->end_date);

        $current = $start->copy()->startOfMonth();
        $months = [
            '01' => 'Janvier',
            '02' => 'Février',
            '03' => 'Mars',
            '04' => 'Avril',
            '05' => 'Mai',
            '06' => 'Juin',
            '07' => 'Juillet',
            '08' => 'Août',
            '09' => 'Septembre',
            '10' => 'Octobre',
            '11' => 'Novembre',
            '12' => 'Décembre',
        ];

        while ($current->lte($end)) {
            $periodStart = $current->copy();
            $periodEnd = $current->copy()->endOfMonth();

            if ($periodEnd->gt($end)) {
                $periodEnd = $end->copy();
            }

            $monthKey = $current->format('m');
            $year = $current->format('Y');
            $monthName = $months[$monthKey];

            AccountingPeriod::create([
                'fiscal_year_id' => $fiscalYear->id,
                'name' => "{$monthName} {$year}",
                'code' => $current->format('Y-m'),
                'start_date' => $periodStart->toDateString(),
                'end_date' => $periodEnd->toDateString(),
                'is_open' => true,
                'is_closed' => false,
            ]);

            $current->addMonth();
        }
    }

    public function render(): View
    {
        return view('livewire.fiscal-years.create');
    }
}
