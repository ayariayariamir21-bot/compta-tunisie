<?php

namespace App\Livewire\FiscalYears;

use App\Enums\AuditAction;
use App\Enums\CompanyRole;
use App\Enums\NotificationSeverity;
use App\Models\FiscalYear;
use App\Notifications\AccountingNotification;
use App\Services\CurrentCompany;
use App\Services\CurrentFiscalYear;
use App\Services\Security\AuditLogService as SecurityAuditLogService;
use App\Services\Security\NotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Index extends Component
{
    public function activate(FiscalYear $fiscalYear, CurrentFiscalYear $currentFiscalYear): void
    {
        if (Auth::user()->cannot('activate', $fiscalYear)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());

        if (! $currentCompany || $fiscalYear->company_id !== $currentCompany->id) {
            abort(403);
        }

        FiscalYear::where('company_id', $currentCompany->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $fiscalYear->update(['is_active' => true]);

        $currentFiscalYear->set(Auth::user(), $fiscalYear->id);

        app(SecurityAuditLogService::class)->logAction(
            AuditAction::FiscalYearActivated,
            "Exercice activé : {$fiscalYear->name}.",
            company: $currentCompany,
            entity: $fiscalYear,
        );

        session()->flash('success', "L'exercice « {$fiscalYear->name} » est maintenant actif.");

        $this->redirect(route('fiscal-years.index'), navigate: true);
    }

    public function close(FiscalYear $fiscalYear): void
    {
        if (Auth::user()->cannot('close', $fiscalYear)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());

        if (! $currentCompany || $fiscalYear->company_id !== $currentCompany->id) {
            abort(403);
        }

        $fiscalYear->update(['is_closed' => true, 'is_active' => false]);

        $currentFiscalYear = app(CurrentFiscalYear::class);
        $current = $currentFiscalYear->get(Auth::user());

        if ($current && $current->id === $fiscalYear->id) {
            $currentFiscalYear->clear();
        }

        app(SecurityAuditLogService::class)->logAction(
            AuditAction::FiscalYearClosed,
            "Exercice clôturé : {$fiscalYear->name}.",
            company: $currentCompany,
            entity: $fiscalYear,
        );

        app(NotificationService::class)->notifyCompanyRoles(
            $currentCompany,
            [CompanyRole::Admin, CompanyRole::Accountant],
            new AccountingNotification(
                title: 'Exercice clôturé',
                message: "L'exercice « {$fiscalYear->name} » a été clôturé.",
                severity: NotificationSeverity::Warning,
                dedupKey: "fiscal_year_closed.{$fiscalYear->id}",
                companyId: $currentCompany->id,
                entityType: 'fiscal_year',
                entityId: $fiscalYear->id,
                routeName: 'fiscal-years.index',
            ),
            exceptUserId: (int) Auth::id(),
        );

        session()->flash('success', "L'exercice « {$fiscalYear->name} » a été clôturé.");

        $this->redirect(route('fiscal-years.index'), navigate: true);
    }

    public function render(CurrentCompany $currentCompany): View
    {
        $company = $currentCompany->get(Auth::user());

        return view('livewire.fiscal-years.index', [
            'fiscalYears' => $company
                ? FiscalYear::where('company_id', $company->id)
                    ->orderByDesc('start_date')
                    ->get()
                : collect(),
            'currentCompany' => $company,
        ]);
    }
}
