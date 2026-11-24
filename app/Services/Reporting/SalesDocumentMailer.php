<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Mail\SalesDocumentMail;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Quote;
use App\Support\Decimal;
use App\Support\PdfFormat;
use Illuminate\Support\Facades\Mail;

/**
 * Emails sales documents to the customer with the PDF attached.
 */
final class SalesDocumentMailer
{
    public function __construct(private SalesDocumentPdfService $pdfService) {}

    public function sendInvoice(Invoice $invoice): string
    {
        if ($invoice->status !== InvoiceStatus::POSTED) {
            throw new \InvalidArgumentException('Seule une facture comptabilisée peut être envoyée au client.');
        }

        $invoice->loadMissing(['company', 'customer']);

        return $this->send('Facture', $invoice->invoice_number, $invoice->company, $invoice->customer, (string) $invoice->total, $invoice->currency, $this->pdfService->invoice($invoice));
    }

    public function sendQuote(Quote $quote): string
    {
        $quote->loadMissing(['company', 'customer']);

        return $this->send('Devis', $quote->quote_number, $quote->company, $quote->customer, (string) $quote->total, $quote->currency, $this->pdfService->quote($quote));
    }

    public function sendCreditNote(CreditNote $creditNote): string
    {
        if ($creditNote->status !== CreditNoteStatus::POSTED) {
            throw new \InvalidArgumentException('Seul un avoir comptabilisé peut être envoyé au client.');
        }

        $creditNote->loadMissing(['company', 'customer']);

        return $this->send('Facture d\'avoir', $creditNote->credit_note_number, $creditNote->company, $creditNote->customer, (string) $creditNote->total, $creditNote->currency, $this->pdfService->creditNote($creditNote));
    }

    /**
     * @param  array{content: string, filename: string}  $pdf
     * @return string The recipient address
     */
    private function send(string $label, string $number, Company $company, Customer $customer, string $total, string $currency, array $pdf): string
    {
        $email = trim((string) $customer->email);

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException("Le client « {$customer->name} » n'a pas d'adresse e-mail valide. Renseignez-la dans sa fiche.");
        }

        $companyEmail = trim((string) $company->email);

        Mail::to($email, $customer->name)->send(new SalesDocumentMail(
            documentLabel: $label,
            documentNumber: $number,
            companyName: $company->legal_name ?: $company->name,
            customerName: $customer->legal_name ?: $customer->name,
            totalLabel: PdfFormat::money(Decimal::round($total)).' '.$currency,
            pdfContent: $pdf['content'],
            pdfFilename: $pdf['filename'],
            replyToAddress: filter_var($companyEmail, FILTER_VALIDATE_EMAIL) !== false ? $companyEmail : null,
        ));

        return $email;
    }
}
