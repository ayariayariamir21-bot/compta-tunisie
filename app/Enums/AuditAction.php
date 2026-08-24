<?php

namespace App\Enums;

/**
 * Every audit action that the application actually emits.
 *
 * Actions are grouped by domain; security-sensitive actions (authentication,
 * two-factor, membership management) are only visible to company admins.
 */
enum AuditAction: string
{
    // Authentication
    case Login = 'login';
    case LoginFailed = 'login_failed';
    case Logout = 'logout';
    case TwoFactorEnabled = 'two_factor_enabled';
    case TwoFactorDisabled = 'two_factor_disabled';

    // Company & membership
    case CompanyCreated = 'company_created';
    case CompanyUpdated = 'company_updated';
    case CompanyActivated = 'company_activated';
    case CompanyDeactivated = 'company_deactivated';
    case RoleChanged = 'role_changed';
    case MemberActivated = 'member_activated';
    case MemberDeactivated = 'member_deactivated';
    case MemberRemoved = 'member_removed';

    // Accounting structure
    case FiscalYearCreated = 'fiscal_year_created';
    case FiscalYearUpdated = 'fiscal_year_updated';
    case FiscalYearClosed = 'fiscal_year_closed';
    case FiscalYearActivated = 'fiscal_year_activated';
    case AccountingPeriodClosed = 'accounting_period_closed';
    case AccountCreated = 'account_created';
    case AccountUpdated = 'account_updated';
    case AccountDeactivated = 'account_deactivated';
    case JournalCreated = 'journal_created';
    case JournalUpdated = 'journal_updated';
    case TaxRateCreated = 'tax_rate_created';
    case TaxRateUpdated = 'tax_rate_updated';
    case PaymentMethodCreated = 'payment_method_created';
    case PaymentMethodUpdated = 'payment_method_updated';
    case SettingsUpdated = 'settings_updated';

    // Transactions
    case JournalEntryCreated = 'journal_entry_created';
    case JournalEntryPosted = 'journal_entry_posted';
    case JournalEntryCancelled = 'journal_entry_cancelled';
    case InvoiceCreated = 'invoice_created';
    case InvoiceUpdated = 'invoice_updated';
    case InvoicePosted = 'invoice_posted';
    case InvoiceCancelled = 'invoice_cancelled';
    case CreditNoteCreated = 'credit_note_created';
    case CreditNotePosted = 'credit_note_posted';
    case PurchaseInvoiceCreated = 'purchase_invoice_created';
    case PurchaseInvoicePosted = 'purchase_invoice_posted';
    case CustomerPaymentCreated = 'customer_payment_created';
    case CustomerPaymentPosted = 'customer_payment_posted';
    case CustomerPaymentCancelled = 'customer_payment_cancelled';
    case SupplierPaymentCreated = 'supplier_payment_created';
    case SupplierPaymentPosted = 'supplier_payment_posted';
    case SupplierPaymentCancelled = 'supplier_payment_cancelled';
    case ExpenseCreated = 'expense_created';
    case ExpensePosted = 'expense_posted';
    case ExpenseCancelled = 'expense_cancelled';

    // Backups & restore (system-level operations, not company-scoped)
    case BackupCreated = 'backup_created';
    case BackupValidated = 'backup_validated';
    case BackupDownloaded = 'backup_downloaded';
    case BackupRestoreStarted = 'backup_restore_started';
    case BackupRestoreSucceeded = 'backup_restore_succeeded';
    case BackupRestoreFailed = 'backup_restore_failed';

    public function label(): string
    {
        return match ($this) {
            self::Login => 'Connexion',
            self::LoginFailed => 'Échec de connexion',
            self::Logout => 'Déconnexion',
            self::TwoFactorEnabled => 'Authentification à deux facteurs activée',
            self::TwoFactorDisabled => 'Authentification à deux facteurs désactivée',
            self::CompanyCreated => 'Société créée',
            self::CompanyUpdated => 'Société modifiée',
            self::CompanyActivated => 'Société activée',
            self::CompanyDeactivated => 'Société désactivée',
            self::RoleChanged => 'Rôle d\'un membre modifié',
            self::MemberActivated => 'Membre réactivé',
            self::MemberDeactivated => 'Membre désactivé',
            self::MemberRemoved => 'Membre retiré',
            self::FiscalYearCreated => 'Exercice créé',
            self::FiscalYearUpdated => 'Exercice modifié',
            self::FiscalYearClosed => 'Exercice clôturé',
            self::FiscalYearActivated => 'Exercice activé',
            self::AccountingPeriodClosed => 'Période clôturée',
            self::AccountCreated => 'Compte créé',
            self::AccountUpdated => 'Compte modifié',
            self::AccountDeactivated => 'Compte désactivé',
            self::JournalCreated => 'Journal créé',
            self::JournalUpdated => 'Journal modifié',
            self::TaxRateCreated => 'Taux de TVA créé',
            self::TaxRateUpdated => 'Taux de TVA modifié',
            self::PaymentMethodCreated => 'Mode de paiement créé',
            self::PaymentMethodUpdated => 'Mode de paiement modifié',
            self::SettingsUpdated => 'Paramètres comptables modifiés',
            self::JournalEntryCreated => 'Écriture créée',
            self::JournalEntryPosted => 'Écriture comptabilisée',
            self::JournalEntryCancelled => 'Écriture annulée',
            self::InvoiceCreated => 'Facture créée',
            self::InvoiceUpdated => 'Facture modifiée',
            self::InvoicePosted => 'Facture comptabilisée',
            self::InvoiceCancelled => 'Facture annulée',
            self::CreditNoteCreated => 'Avoir créé',
            self::CreditNotePosted => 'Avoir comptabilisé',
            self::PurchaseInvoiceCreated => 'Facture d\'achat créée',
            self::PurchaseInvoicePosted => 'Facture d\'achat comptabilisée',
            self::CustomerPaymentCreated => 'Règlement client créé',
            self::CustomerPaymentPosted => 'Règlement client comptabilisé',
            self::CustomerPaymentCancelled => 'Règlement client annulé',
            self::SupplierPaymentCreated => 'Règlement fournisseur créé',
            self::SupplierPaymentPosted => 'Règlement fournisseur comptabilisé',
            self::SupplierPaymentCancelled => 'Règlement fournisseur annulé',
            self::ExpenseCreated => 'Dépense créée',
            self::ExpensePosted => 'Dépense comptabilisée',
            self::ExpenseCancelled => 'Dépense annulée',
            self::BackupCreated => 'Sauvegarde créée',
            self::BackupValidated => 'Sauvegarde vérifiée',
            self::BackupDownloaded => 'Sauvegarde téléchargée',
            self::BackupRestoreStarted => 'Restauration démarrée',
            self::BackupRestoreSucceeded => 'Restauration réussie',
            self::BackupRestoreFailed => 'Restauration échouée',
        };
    }

    /**
     * Security-sensitive actions are restricted to company admins;
     * accountants only see business and accounting activity.
     */
    public function isSecuritySensitive(): bool
    {
        return match ($this) {
            self::Login, self::LoginFailed, self::Logout, self::TwoFactorEnabled, self::TwoFactorDisabled,
            self::CompanyCreated, self::CompanyUpdated, self::CompanyActivated, self::CompanyDeactivated,
            self::RoleChanged, self::MemberActivated, self::MemberDeactivated, self::MemberRemoved,
            self::BackupCreated, self::BackupValidated, self::BackupDownloaded,
            self::BackupRestoreStarted, self::BackupRestoreSucceeded, self::BackupRestoreFailed => true,
            default => false,
        };
    }

    /**
     * @return array<array-key, array{value: string, label: string}>
     */
    public static function optionsForFilter(): array
    {
        return collect(self::cases())
            ->map(fn (self $action): array => ['value' => $action->value, 'label' => $action->label()])
            ->all();
    }
}
