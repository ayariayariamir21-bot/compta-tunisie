<?php

namespace App\Livewire\FiscalYears;

use App\Enums\AuditAction;
use App\Models\FiscalYear;
use App\Services\CurrentCompany;
use App\Services\Security\AuditLogService as SecurityAuditLogService;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Edit extends Component
{
    public ?FiscalYear $fiscalYear = null;

    public string $name = '';

    public string $code = '';

    public string $start_date = '';

    public string $end_date = '';

    public function mount(int $fiscalYearId, CurrentCompany $currentCompany): void
    {
        $fiscalYear = FiscalYear::findOrFail($fiscalYearId);

        $company = $currentCompany->get(Auth::user());

        if (! $company || $fiscalYear->company_id !== $company->id) {
            abort(403);
        }

        if (Auth::user()->cannot('update', $fiscalYear)) {
            abort(403);
        }

        $this->fiscalYear = $fiscalYear;
        $this->name = $fiscalYear->name;
        $this->code = $fiscalYear->code;
        $this->start_date = Carbon::parse($fiscalYear->start_date)->format('Y-m-d');
        $this->end_date = Carbon::parse($fiscalYear->end_date)->format('Y-m-d');
    }

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

    public function update(SecurityAuditLogService $auditLog): void
    {
        if (Auth::user()->cannot('update', $this->fiscalYear)) {
            abort(403);
        }

        $validated = $this->validate();

        $hasOverlap = FiscalYear::where('company_id', $this->fiscalYear->company_id)
            ->where('code', $validated['code'])
            ->where('id', '!=', $this->fiscalYear->id)
            ->exists();

        if ($hasOverlap) {
            $this->addError('code', 'Un exercice avec ce code existe déjà pour cette société.');

            return;
        }

        $dateOverlap = FiscalYear::where('company_id', $this->fiscalYear->company_id)
            ->where('id', '!=', $this->fiscalYear->id)
            ->where('start_date', '<=', $validated['end_date'])
            ->where('end_date', '>=', $validated['start_date'])
            ->exists();

        if ($dateOverlap) {
            $this->addError('start_date', 'Cet exercice chevauche un exercice existant.');

            return;
        }

        $hasPeriods = $this->fiscalYear->accountingPeriods()->exists();

        if ($hasPeriods && (
            $validated['start_date'] !== Carbon::parse($this->fiscalYear->start_date)->format('Y-m-d')
            || $validated['end_date'] !== Carbon::parse($this->fiscalYear->end_date)->format('Y-m-d')
        )) {
            $this->addError('start_date', 'Impossible de modifier les dates d\'un exercice comportant déjà des périodes comptables.');

            return;
        }

        $before = $auditLog->snapshot($this->fiscalYear, ['name', 'code', 'start_date', 'end_date']);

        $this->fiscalYear->update($validated);

        $this->fiscalYear = $this->fiscalYear->fresh();

        $auditLog->logModelUpdated(
            $this->fiscalYear,
            AuditAction::FiscalYearUpdated,
            $before,
            $auditLog->snapshot($this->fiscalYear, ['name', 'code', 'start_date', 'end_date']),
        );

        session()->flash('success', "L'exercice « {$this->fiscalYear->name} » a été mis à jour.");

        $this->redirect(route('fiscal-years.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.fiscal-years.edit');
    }
}
