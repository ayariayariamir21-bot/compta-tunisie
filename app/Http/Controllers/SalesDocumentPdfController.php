<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\CurrentCompany;
use App\Services\Reporting\SalesDocumentPdfService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Download endpoints for printable documents. Documents are always resolved
 * inside the user's current company and authorized with their policy.
 */
final class SalesDocumentPdfController extends Controller
{
    public function __construct(
        private SalesDocumentPdfService $pdfService,
        private CurrentCompany $currentCompany,
    ) {}

    public function invoice(Request $request, int $invoiceId): Response
    {
        [$user, $companyId] = $this->context($request);
        $invoice = Invoice::where('company_id', $companyId)->findOrFail($invoiceId);
        $this->ensureCanView($user, $invoice);

        return $this->pdfResponse($this->pdfService->invoice($invoice));
    }

    public function quote(Request $request, int $quoteId): Response
    {
        [$user, $companyId] = $this->context($request);
        $quote = Quote::where('company_id', $companyId)->findOrFail($quoteId);
        $this->ensureCanView($user, $quote);

        return $this->pdfResponse($this->pdfService->quote($quote));
    }

    public function creditNote(Request $request, int $creditNoteId): Response
    {
        [$user, $companyId] = $this->context($request);
        $creditNote = CreditNote::where('company_id', $companyId)->findOrFail($creditNoteId);
        $this->ensureCanView($user, $creditNote);

        return $this->pdfResponse($this->pdfService->creditNote($creditNote));
    }

    public function withholdingCertificate(Request $request, int $supplierPaymentId): Response
    {
        [$user, $companyId] = $this->context($request);
        $payment = SupplierPayment::where('company_id', $companyId)->findOrFail($supplierPaymentId);
        $this->ensureCanView($user, $payment);

        try {
            return $this->pdfResponse($this->pdfService->withholdingCertificate($payment));
        } catch (\InvalidArgumentException) {
            abort(404);
        }
    }

    /**
     * Authenticated user and their current company: documents of other
     * companies are never reachable, even by guessing an id.
     *
     * @return array{0: User, 1: int}
     */
    private function context(Request $request): array
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(404);
        }

        $company = $this->currentCompany->get($user);
        if ($company === null) {
            abort(404);
        }

        return [$user, $company->id];
    }

    private function ensureCanView(User $user, Model $document): void
    {
        if ($user->cannot('view', $document)) {
            abort(404);
        }
    }

    /**
     * @param  array{content: string, filename: string}  $export
     */
    private function pdfResponse(array $export): Response
    {
        return response($export['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$export['filename'].'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }
}
