<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\JournalType;
use App\Enums\PaymentMethodType;
use App\Enums\PurchaseInvoiceStatus;
use App\Enums\SupplierPaymentStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\CompanyAccountingSetting;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\PaymentMethod;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use App\Services\Accounting\SupplierPaymentPostingService;
use App\Services\Security\AuditLogService;
use App\Services\Security\AuditLogService as SecurityAuditLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SupplierPaymentService
{
    public function __construct(
        private SupplierPaymentPostingService $postingService,
        private ?AuditLogService $auditLog = null,
    ) {}

    private function audits(): SecurityAuditLogService
    {
        return $this->auditLog ?? new AuditLogService;
    }

    /**
     * Numbering strategy: REG-ACH-{YEAR}-{NNNNNN} scoped per company
     * (REG ACH = Règlement Achats). The next number is derived from the highest
     * existing number for the current prefix (all statuses included, so numbers
     * are never reused), then uniqueness is verified before returning; the
     * database unique constraint on (company_id, payment_number) backstops
     * concurrent generations.
     */
    public function generatePaymentNumber(int $companyId): string
    {
        $year = (int) now()->year;
        $prefix = "REG-ACH-{$year}-";
        $maxTries = 10;

        $maxNumber = SupplierPayment::where('company_id', $companyId)
            ->where('payment_number', 'like', $prefix.'%')
            ->pluck('payment_number')
            ->map(fn (string $num) => (int) substr($num, strlen($prefix)))
            ->filter(fn (int $num) => $num > 0)
            ->max() ?? 0;

        for ($attempt = 0; $attempt < $maxTries; $attempt++) {
            $maxNumber++;
            $candidate = $prefix.str_pad((string) $maxNumber, 6, '0', STR_PAD_LEFT);
            if (! SupplierPayment::where('company_id', $companyId)->where('payment_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Impossible de générer un numéro de règlement unique après '.$maxTries.' tentatives.');
    }

    /**
     * @param  array{company_id: int, supplier_id: int, fiscal_year_id: int, accounting_period_id: int, payment_method_id: int, journal_id?: int|null, destination_account_id?: int|null, payment_date: string, amount: string, currency?: string|null, reference?: string|null, notes?: string|null, created_by: int, allocations?: array<int, array{purchase_invoice_id: int, amount: string}>}  $data
     *
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public function createDraft(array $data): SupplierPayment
    {
        $companyId = (int) $data['company_id'];

        $supplier = $this->validateSupplier((int) $data['supplier_id'], $companyId);
        $fiscalYear = $this->validateFiscalYear((int) $data['fiscal_year_id'], $companyId);
        $period = $this->validateAccountingPeriod((int) $data['accounting_period_id'], $fiscalYear->id);
        $this->validatePeriodOpen($period->id);
        $this->validateDateInPeriod((string) $data['payment_date'], $period);

        $method = $this->validatePaymentMethod((int) $data['payment_method_id'], $companyId);

        if (! empty($data['journal_id'])) {
            $journal = $this->validateJournal((int) $data['journal_id'], $companyId, $fiscalYear->id);
        } else {
            $resolved = $this->resolveJournal($method, $companyId, $fiscalYear->id);

            if ($resolved === null) {
                throw new \InvalidArgumentException(
                    'Aucun journal n\'a pu être déterminé pour ce moyen de paiement. Veuillez sélectionner explicitement un journal.'
                );
            }

            $journal = $resolved;
        }

        if (! empty($data['destination_account_id'])) {
            $destination = $this->validateDestinationAccount((int) $data['destination_account_id'], $companyId, $fiscalYear->id);
        } else {
            $resolvedDestination = $this->resolveDestinationAccount($method, $companyId, $fiscalYear->id);

            if ($resolvedDestination === null) {
                throw new \InvalidArgumentException(
                    'Aucun compte de trésorerie (banque/caisse) de destination n\'a pu être déterminé pour ce moyen de paiement. Veuillez en sélectionner un ou le configurer dans les paramètres comptables.'
                );
            }

            $destination = $resolvedDestination;
        }

        $amount = $this->toDecimal((string) $data['amount']);
        if (bccomp($amount, '0', 3) <= 0) {
            throw new \InvalidArgumentException('Le montant du règlement doit être strictement positif.');
        }

        $allocationsData = $data['allocations'] ?? [];
        $this->validateAllocations($companyId, $supplier->id, $amount, $allocationsData);

        return DB::transaction(function () use ($data, $companyId, $supplier, $fiscalYear, $period, $method, $journal, $destination, $amount, $allocationsData): SupplierPayment {
            $payment = SupplierPayment::create([
                'company_id' => $companyId,
                'supplier_id' => $supplier->id,
                'fiscal_year_id' => $fiscalYear->id,
                'accounting_period_id' => $period->id,
                'payment_method_id' => $method->id,
                'journal_id' => $journal->id,
                'destination_account_id' => $destination->id,
                'payment_number' => $this->generatePaymentNumber($companyId),
                'payment_date' => $data['payment_date'],
                'amount' => $amount,
                'currency' => $data['currency'] ?? $supplier->company->currency ?? 'TND',
                'reference' => ($data['reference'] ?? null) === '' ? null : ($data['reference'] ?? null),
                'notes' => ($data['notes'] ?? null) === '' ? null : ($data['notes'] ?? null),
                'status' => SupplierPaymentStatus::DRAFT,
                'created_by' => (int) $data['created_by'],
            ]);

            foreach ($allocationsData as $entry) {
                SupplierPaymentAllocation::create([
                    'supplier_payment_id' => $payment->id,
                    'purchase_invoice_id' => (int) $entry['purchase_invoice_id'],
                    'amount' => $this->toDecimal((string) $entry['amount']),
                ]);
            }

            $payment = $payment->fresh(['allocations.purchaseInvoice', 'supplier', 'paymentMethod', 'journal', 'destinationAccount']);

            $this->audits()->logModelCreated($payment, AuditAction::SupplierPaymentCreated);

            return $payment;
        });
    }

    /**
     * @param  array{payment_date?: string, amount?: string, reference?: string|null, notes?: string|null, allocations?: array<int, array{purchase_invoice_id: int, amount: string}>}  $data
     *
     * @throws \InvalidArgumentException
     */
    public function updateDraft(SupplierPayment $payment, array $data): SupplierPayment
    {
        if ($payment->status !== SupplierPaymentStatus::DRAFT) {
            throw new \InvalidArgumentException('Seul un règlement en brouillon peut être modifié.');
        }

        return DB::transaction(function () use ($payment, $data): SupplierPayment {
            $updates = [];

            if (isset($data['payment_date']) && $data['payment_date'] !== '') {
                $this->validateDateInPeriod((string) $data['payment_date'], $payment->accountingPeriod);
                $updates['payment_date'] = $data['payment_date'];
            }

            if (isset($data['amount'])) {
                $amount = $this->toDecimal((string) $data['amount']);
                if (bccomp($amount, '0', 3) <= 0) {
                    throw new \InvalidArgumentException('Le montant du règlement doit être strictement positif.');
                }
                $updates['amount'] = $amount;
            }

            foreach (['reference', 'notes'] as $nullableField) {
                if (array_key_exists($nullableField, $data)) {
                    $updates[$nullableField] = ($data[$nullableField] ?? null) === '' ? null : $data[$nullableField];
                }
            }

            if ($updates !== []) {
                $payment->update($updates);
            }

            if (array_key_exists('allocations', $data)) {
                $this->syncAllocations(
                    $payment->fresh(),
                    $data['allocations']
                );
            }

            return $payment->fresh(['allocations.purchaseInvoice', 'supplier', 'paymentMethod', 'journal', 'destinationAccount']);
        });
    }

    /**
     * Create a DRAFT payment from a posted purchase invoice.
     *
     * The supplier and the invoice allocation are preselected with the invoice's
     * remaining balance; the payment is never posted automatically.
     *
     * @throws \InvalidArgumentException
     */
    public function createFromInvoice(
        PurchaseInvoice $invoice,
        int $companyId,
        int $fiscalYearId,
        int $accountingPeriodId,
        int $createdBy,
        ?string $paymentDate = null,
    ): SupplierPayment {
        if ($invoice->company_id !== $companyId) {
            throw new \InvalidArgumentException('La facture sélectionnée n\'appartient pas à cette société.');
        }

        if ($invoice->status !== PurchaseInvoiceStatus::POSTED) {
            throw new \InvalidArgumentException('Seule une facture comptabilisée peut faire l\'objet d\'un règlement.');
        }

        $remaining = $this->getInvoiceRemainingAmount($invoice->fresh());
        if (bccomp($remaining, '0', 3) <= 0) {
            throw new \InvalidArgumentException('Cette facture est déjà totalement réglée.');
        }

        /** @var PaymentMethod|null $method */
        $method = PaymentMethod::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if ($method === null) {
            throw new \InvalidArgumentException('Aucun moyen de paiement actif n\'est configuré pour cette société.');
        }

        $journal = $this->resolveJournal($method, $companyId, $fiscalYearId);
        if ($journal === null) {
            throw new \InvalidArgumentException('Aucun journal banque/caisse actif n\'a pu être déterminé pour ce moyen de paiement.');
        }

        $destination = $this->resolveDestinationAccount($method, $companyId, $fiscalYearId);
        if ($destination === null) {
            throw new \InvalidArgumentException(
                'Aucun compte de trésorerie (banque/caisse) de destination n\'est configuré pour cette société.'
            );
        }

        $fiscalYear = $this->validateFiscalYear($fiscalYearId, $companyId);
        $period = $this->validateAccountingPeriod($accountingPeriodId, $fiscalYearId);
        $this->validatePeriodOpen($period->id);

        $date = $paymentDate ?? now()->toDateString();
        $this->validateDateInPeriod($date, $period);

        return $this->createDraft([
            'company_id' => $companyId,
            'supplier_id' => $invoice->supplier_id,
            'fiscal_year_id' => $fiscalYearId,
            'accounting_period_id' => $accountingPeriodId,
            'payment_method_id' => $method->id,
            'journal_id' => $journal->id,
            'destination_account_id' => $destination->id,
            'payment_date' => $date,
            'amount' => $remaining,
            'currency' => $invoice->currency,
            'created_by' => $createdBy,
            'allocations' => [
                ['purchase_invoice_id' => $invoice->id, 'amount' => $remaining],
            ],
        ]);
    }

    /**
     * Add or replace the allocation of a single purchase invoice on a draft
     * payment. A non-positive amount removes the allocation.
     *
     * @throws \InvalidArgumentException
     */
    public function allocate(SupplierPayment $payment, int $purchaseInvoiceId, string $amount): SupplierPayment
    {
        if ($payment->status !== SupplierPaymentStatus::DRAFT) {
            throw new \InvalidArgumentException('Seul un règlement en brouillon peut être imputé.');
        }

        return DB::transaction(function () use ($payment, $purchaseInvoiceId, $amount): SupplierPayment {
            $invoice = PurchaseInvoice::findOrFail($purchaseInvoiceId);

            if ($invoice->company_id !== $payment->company_id) {
                throw new \InvalidArgumentException('La facture sélectionnée n\'appartient pas à cette société.');
            }

            if ($invoice->supplier_id !== $payment->supplier_id) {
                throw new \InvalidArgumentException('La facture sélectionnée n\'appartient pas à ce fournisseur.');
            }

            $normalized = $this->toDecimal($amount);

            $allocationsData = [];
            foreach ($payment->allocations()->get() as $row) {
                if ((int) $row->purchase_invoice_id === $purchaseInvoiceId) {
                    continue;
                }
                $allocationsData[] = ['purchase_invoice_id' => (int) $row->purchase_invoice_id, 'amount' => (string) $row->amount];
            }

            if (bccomp($normalized, '0', 3) > 0) {
                $allocationsData[] = ['purchase_invoice_id' => $purchaseInvoiceId, 'amount' => $normalized];
            }

            $this->validateAllocations(
                $payment->company_id,
                $payment->supplier_id,
                (string) $payment->amount,
                $allocationsData
            );

            SupplierPaymentAllocation::updateOrCreate([
                'supplier_payment_id' => $payment->id,
                'purchase_invoice_id' => $purchaseInvoiceId,
            ], [
                'amount' => $normalized,
            ]);

            if (bccomp($normalized, '0', 3) <= 0) {
                SupplierPaymentAllocation::where('supplier_payment_id', $payment->id)
                    ->where('purchase_invoice_id', $purchaseInvoiceId)
                    ->delete();
            }

            return $payment->fresh(['allocations.purchaseInvoice']);
        });
    }

    /**
     * Validate proposed allocations against company/supplier/invoice rules,
     * per-invoice outstanding balances and the payment amount itself.
     *
     * @param  array<int, array{purchase_invoice_id: int, amount: string}>  $allocationsData
     *
     * @throws \InvalidArgumentException
     */
    public function validateAllocations(int $companyId, int $supplierId, string $paymentAmount, array $allocationsData): void
    {
        if ($allocationsData === []) {
            return;
        }

        $seenInvoiceIds = [];
        $total = '0';

        foreach ($allocationsData as $entry) {
            $invoiceId = (int) $entry['purchase_invoice_id'];

            if (in_array($invoiceId, $seenInvoiceIds, true)) {
                throw new \InvalidArgumentException('Chaque facture ne peut être imputée qu\'une seule fois par règlement.');
            }
            $seenInvoiceIds[] = $invoiceId;

            $invoice = PurchaseInvoice::find($invoiceId);
            if (! $invoice) {
                throw new \InvalidArgumentException('La facture à imputer est introuvable.');
            }

            if ($invoice->company_id !== $companyId) {
                throw new \InvalidArgumentException('La facture à imputer n\'appartient pas à cette société.');
            }

            if ($invoice->status !== PurchaseInvoiceStatus::POSTED) {
                throw new \InvalidArgumentException('Seule une facture comptabilisée peut faire l\'objet d\'une imputation.');
            }

            if ($invoice->supplier_id !== $supplierId) {
                throw new \InvalidArgumentException('La facture à imputer n\'appartient pas au fournisseur du règlement.');
            }

            $amount = $this->toDecimal((string) $entry['amount']);
            if (bccomp($amount, '0', 3) <= 0) {
                throw new \InvalidArgumentException('Le montant imputé doit être strictement positif.');
            }

            $remaining = $this->getInvoiceRemainingAmount($invoice);
            if (bccomp($amount, $remaining, 3) > 0) {
                throw new \InvalidArgumentException(
                    'Imputation supérieure au reste à régler de la facture '.$invoice->invoice_number.' (reste : '.$remaining.').'
                );
            }

            $total = bcadd($total, $amount, 3);
        }

        if (bccomp($total, $this->toDecimal($paymentAmount), 3) > 0) {
            throw new \InvalidArgumentException(
                'Le total des imputations ('.$total.') dépasse le montant du règlement ('.$this->toDecimal($paymentAmount).').'
            );
        }
    }

    /**
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public function post(SupplierPayment $payment, int $userId): SupplierPayment
    {
        return $this->postingService->post($payment, $userId);
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function cancel(SupplierPayment $payment): SupplierPayment
    {
        if ($payment->status !== SupplierPaymentStatus::DRAFT) {
            throw new \InvalidArgumentException('Seul un règlement en brouillon peut être annulé.');
        }

        DB::transaction(function () use ($payment): void {
            $payment->update(['status' => SupplierPaymentStatus::CANCELLED]);

            $this->audits()->logAction(
                AuditAction::SupplierPaymentCancelled,
                "R\u{e8}glement fournisseur annul\u{e9} : {$payment->payment_number}.",
                entity: $payment,
                metadata: ['payment_number' => $payment->payment_number],
            );
        });

        return $payment->fresh();
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function deleteDraft(SupplierPayment $payment): void
    {
        if ($payment->status !== SupplierPaymentStatus::DRAFT) {
            throw new \InvalidArgumentException('Seul un règlement en brouillon peut être supprimé.');
        }

        DB::transaction(function () use ($payment): void {
            $payment->allocations()->delete();
            $payment->delete();
        });
    }

    // ---------- Outstanding amounts ----------

    /**
     * Allocated amount from POSTED payments only (draft/cancelled payments excluded).
     *
     * @return numeric-string
     */
    public function getPostedAllocatedAmount(PurchaseInvoice $invoice): string
    {
        $value = DB::table('supplier_payment_allocations')
            ->join('supplier_payments', 'supplier_payments.id', '=', 'supplier_payment_allocations.supplier_payment_id')
            ->where('supplier_payment_allocations.purchase_invoice_id', $invoice->id)
            ->where('supplier_payments.status', SupplierPaymentStatus::POSTED->value)
            ->sum('supplier_payment_allocations.amount');

        return $this->decimalFromDb($value);
    }

    /**
     * Remaining amount to settle on a purchase invoice:
     * total − posted payment allocations.
     *
     * @return numeric-string
     */
    public function getInvoiceRemainingAmount(PurchaseInvoice $invoice): string
    {
        $total = $this->toDecimal((string) $invoice->total);
        $allocated = $this->getPostedAllocatedAmount($invoice);

        $remaining = bcsub($total, $allocated, 3);

        return bccomp($remaining, '0', 3) < 0 ? '0.000' : $remaining;
    }

    /**
     * Outstanding balance of a supplier:
     * posted purchase invoices − posted payment allocations.
     *
     * @return numeric-string
     */
    public function getSupplierOutstandingBalance(Supplier $supplier): string
    {
        $invoiceIds = PurchaseInvoice::where('supplier_id', $supplier->id)
            ->where('status', PurchaseInvoiceStatus::POSTED->value)
            ->pluck('id');

        if ($invoiceIds->isEmpty()) {
            return '0.000';
        }

        $invoicesTotal = $this->decimalFromDb(
            PurchaseInvoice::whereIn('id', $invoiceIds)->sum('total')
        );

        $paidTotal = $this->decimalFromDb(
            DB::table('supplier_payment_allocations')
                ->join('supplier_payments', 'supplier_payments.id', '=', 'supplier_payment_allocations.supplier_payment_id')
                ->whereIn('supplier_payment_allocations.purchase_invoice_id', $invoiceIds)
                ->where('supplier_payments.status', SupplierPaymentStatus::POSTED->value)
                ->sum('supplier_payment_allocations.amount')
        );

        $outstanding = bcsub($invoicesTotal, $paidTotal, 3);

        return bccomp($outstanding, '0', 3) < 0 ? '0.000' : $outstanding;
    }

    // ---------- Resolution helpers ----------

    /**
     * Journal resolution by payment-method type using company accounting settings:
     * cash → caisse journal; bank transfer / cheque / card / direct debit → banque journal.
     * Falls back to the first active journal of that type when no default is configured.
     * Returns null for « other » methods (explicit journal selection required).
     */
    public function resolveJournal(PaymentMethod $paymentMethod, int $companyId, int $fiscalYearId): ?Journal
    {
        $settings = CompanyAccountingSetting::where('company_id', $companyId)->first();

        $preferredId = match ($paymentMethod->type) {
            PaymentMethodType::CASH => $settings?->default_cash_journal_id,
            PaymentMethodType::BANK_TRANSFER,
            PaymentMethodType::CHEQUE,
            PaymentMethodType::CARD,
            PaymentMethodType::DIRECT_DEBIT => $settings?->default_bank_journal_id,
            PaymentMethodType::OTHER => null,
        };

        if ($preferredId !== null) {
            $journal = Journal::where('id', $preferredId)
                ->where('company_id', $companyId)
                ->where('fiscal_year_id', $fiscalYearId)
                ->where('is_active', true)
                ->first();

            if ($journal instanceof Journal) {
                return $journal;
            }
        }

        $fallbackType = match ($paymentMethod->type) {
            PaymentMethodType::CASH => JournalType::CAISSE,
            PaymentMethodType::BANK_TRANSFER,
            PaymentMethodType::CHEQUE,
            PaymentMethodType::CARD,
            PaymentMethodType::DIRECT_DEBIT => JournalType::BANQUE,
            PaymentMethodType::OTHER => null,
        };

        if ($fallbackType === null) {
            return null;
        }

        return Journal::where('company_id', $companyId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('type', $fallbackType->value)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }

    /**
     * Destination account resolution using company accounting settings:
     * cash → default cash account; bank-like methods → default bank account.
     */
    public function resolveDestinationAccount(PaymentMethod $paymentMethod, int $companyId, int $fiscalYearId): ?Account
    {
        $settings = CompanyAccountingSetting::where('company_id', $companyId)->first();

        $preferredId = match ($paymentMethod->type) {
            PaymentMethodType::CASH => $settings?->default_cash_account_id,
            PaymentMethodType::BANK_TRANSFER,
            PaymentMethodType::CHEQUE,
            PaymentMethodType::CARD,
            PaymentMethodType::DIRECT_DEBIT => $settings?->default_bank_account_id,
            PaymentMethodType::OTHER => null,
        };

        if ($preferredId !== null) {
            $account = Account::where('id', $preferredId)
                ->where('company_id', $companyId)
                ->where('fiscal_year_id', $fiscalYearId)
                ->where('is_active', true)
                ->first();

            if ($account instanceof Account) {
                return $account;
            }
        }

        return null;
    }

    /**
     * Supplier payable account: supplier.account_id first, then the company
     * default supplier account from accounting settings.
     *
     * @throws \InvalidArgumentException
     */
    public function resolveSupplierPayableAccount(Supplier $supplier, int $companyId): int
    {
        if ($supplier->account_id) {
            return $supplier->account_id;
        }

        $settings = CompanyAccountingSetting::where('company_id', $companyId)->first();
        if ($settings && $settings->default_supplier_account_id) {
            return $settings->default_supplier_account_id;
        }

        throw new \InvalidArgumentException('Aucun compte fournisseur configuré pour ce fournisseur. Veuillez assigner un compte de tiers au fournisseur ou configurer un compte fournisseur par défaut dans les paramètres comptables.');
    }

    // ---------- Validation helpers ----------

    /**
     * @throws \InvalidArgumentException
     */
    public function validateSupplier(int $supplierId, int $companyId): Supplier
    {
        $supplier = Supplier::where('id', $supplierId)
            ->where('company_id', $companyId)
            ->first();

        if (! $supplier) {
            throw new \InvalidArgumentException('Le fournisseur sélectionné n\'appartient pas à cette société.');
        }

        if (! $supplier->is_active) {
            throw new \InvalidArgumentException('Le fournisseur sélectionné est inactif. Veuillez choisir un fournisseur actif.');
        }

        return $supplier;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function validatePaymentMethod(int $paymentMethodId, int $companyId): PaymentMethod
    {
        $method = PaymentMethod::where('id', $paymentMethodId)
            ->where('company_id', $companyId)
            ->first();

        if (! $method) {
            throw new \InvalidArgumentException('Le moyen de paiement sélectionné n\'appartient pas à cette société.');
        }

        if (! $method->is_active) {
            throw new \InvalidArgumentException('Le moyen de paiement sélectionné est inactif.');
        }

        return $method;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function validateJournal(int $journalId, int $companyId, int $fiscalYearId): Journal
    {
        $journal = Journal::where('id', $journalId)
            ->where('company_id', $companyId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->first();

        if (! $journal) {
            throw new \InvalidArgumentException('Le journal sélectionné n\'existe pas dans cette société/exercice.');
        }

        if (! $journal->is_active) {
            throw new \InvalidArgumentException('Le journal sélectionné est inactif.');
        }

        return $journal;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function validateDestinationAccount(int $accountId, int $companyId, int $fiscalYearId): Account
    {
        $account = Account::where('id', $accountId)
            ->where('company_id', $companyId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->first();

        if (! $account) {
            throw new \InvalidArgumentException('Le compte de destination sélectionné n\'appartient pas à cette société/exercice.');
        }

        if (! $account->is_active) {
            throw new \InvalidArgumentException('Le compte de destination sélectionné est inactif.');
        }

        return $account;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function validateFiscalYear(int $fiscalYearId, int $companyId): FiscalYear
    {
        $fy = FiscalYear::where('id', $fiscalYearId)
            ->where('company_id', $companyId)
            ->first();

        if (! $fy) {
            throw new \InvalidArgumentException('L\'exercice comptable sélectionné n\'appartient pas à cette société.');
        }

        if ($fy->is_closed) {
            throw new \InvalidArgumentException('L\'exercice comptable est clôturé.');
        }

        return $fy;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function validateAccountingPeriod(int $periodId, int $fiscalYearId): AccountingPeriod
    {
        $period = AccountingPeriod::where('id', $periodId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->first();

        if (! $period) {
            throw new \InvalidArgumentException('La période comptable sélectionnée n\'appartient pas à cet exercice.');
        }

        return $period;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function validatePeriodOpen(int $periodId): void
    {
        $period = AccountingPeriod::findOrFail($periodId);

        if ($period->is_closed || ! $period->is_open) {
            throw new \InvalidArgumentException('La période comptable est clôturée.');
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function validateDateInPeriod(string $date, AccountingPeriod $period): void
    {
        $parsed = Carbon::parse($date);
        $startDate = Carbon::parse($period->start_date);
        $endDate = Carbon::parse($period->end_date);

        if ($parsed->lt($startDate) || $parsed->gt($endDate)) {
            throw new \InvalidArgumentException('La date du règlement doit être comprise dans la période comptable (du '.$startDate->format('d/m/Y').' au '.$endDate->format('d/m/Y').').');
        }
    }

    /**
     * Replace all allocations of a draft payment.
     *
     * @param  array<int, array{purchase_invoice_id: int, amount: string}>  $allocationsData
     *
     * @throws \InvalidArgumentException
     */
    private function syncAllocations(SupplierPayment $payment, array $allocationsData): void
    {
        $this->validateAllocations(
            $payment->company_id,
            $payment->supplier_id,
            (string) $payment->amount,
            $allocationsData
        );

        $payment->allocations()->delete();

        foreach ($allocationsData as $entry) {
            SupplierPaymentAllocation::create([
                'supplier_payment_id' => $payment->id,
                'purchase_invoice_id' => (int) $entry['purchase_invoice_id'],
                'amount' => $this->toDecimal((string) $entry['amount']),
            ]);
        }
    }

    /**
     * Convert a value into a decimal string with exactly 3 decimal places.
     *
     * @return numeric-string
     */
    public function toDecimal(string $value): string
    {
        $normalized = trim(str_replace([',', ' '], ['', ''], $value));
        if ($normalized === '') {
            return '0.000';
        }

        if (! is_numeric($normalized)) {
            throw new \InvalidArgumentException('Le montant saisi n\'est pas numérique.');
        }

        return number_format((float) $normalized, 3, '.', '');
    }

    /**
     * Normalize a database aggregate (SUM over decimal columns) into a fixed
     * 3-decimal numeric string.
     *
     * @return numeric-string
     */
    private function decimalFromDb(mixed $value): string
    {
        return number_format((float) str_replace(',', '', (string) $value), 3, '.', '');
    }
}
