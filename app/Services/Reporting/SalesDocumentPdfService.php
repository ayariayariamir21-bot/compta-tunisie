<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Enums\SupplierPaymentStatus;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\SupplierPayment;
use App\Support\Decimal;
use Dompdf\Dompdf;
use Illuminate\Support\Str;

/**
 * Printable customer-facing documents: invoices, quotes, credit notes and
 * the retenue à la source certificate given to suppliers.
 */
final class SalesDocumentPdfService
{
    /**
     * @return array{content: string, filename: string}
     */
    public function invoice(Invoice $invoice): array
    {
        $invoice->loadMissing(['company', 'customer', 'lines.taxRate']);
        $isDraft = $invoice->status === InvoiceStatus::DRAFT;

        $totals = [
            'Total HT' => Decimal::round((string) $invoice->subtotal),
            'TVA' => Decimal::round((string) $invoice->tax_total),
        ];
        if (bccomp(Decimal::round((string) $invoice->stamp_duty), '0', 3) > 0) {
            $totals['Droit de timbre'] = Decimal::round((string) $invoice->stamp_duty);
        }

        $meta = [];
        if ($invoice->due_date !== null) {
            $meta['Échéance'] = $invoice->due_date->format('d/m/Y');
        }

        return $this->render('pdf.documents.sales-document', [
            'company' => $invoice->company,
            'title' => 'FACTURE',
            'number' => $invoice->invoice_number,
            'date' => $invoice->invoice_date,
            'meta' => $meta,
            'isDraft' => $isDraft,
            'partyLabel' => 'Client',
            'party' => $invoice->customer,
            'lines' => $invoice->lines,
            'totals' => $totals,
            'grandTotalLabel' => 'Total TTC',
            'grandTotal' => Decimal::round((string) $invoice->total),
            'currency' => $invoice->currency,
            'wordsIntro' => 'Arrêtée la présente facture à la somme de',
            'notes' => $invoice->notes,
            'terms' => $invoice->terms,
        ], 'facture-'.($isDraft ? 'brouillon-'.$invoice->id : $invoice->invoice_number));
    }

    /**
     * @return array{content: string, filename: string}
     */
    public function quote(Quote $quote): array
    {
        $quote->loadMissing(['company', 'customer', 'lines.taxRate']);

        $meta = [];
        if ($quote->valid_until !== null) {
            $meta['Valable jusqu\'au'] = $quote->valid_until->format('d/m/Y');
        }

        return $this->render('pdf.documents.sales-document', [
            'company' => $quote->company,
            'title' => 'DEVIS',
            'number' => $quote->quote_number,
            'date' => $quote->quote_date,
            'meta' => $meta,
            'isDraft' => false,
            'partyLabel' => 'Client',
            'party' => $quote->customer,
            'lines' => $quote->lines,
            'totals' => [
                'Total HT' => Decimal::round((string) $quote->subtotal),
                'TVA' => Decimal::round((string) $quote->tax_total),
            ],
            'grandTotalLabel' => 'Total TTC',
            'grandTotal' => Decimal::round((string) $quote->total),
            'currency' => $quote->currency,
            'wordsIntro' => 'Arrêté le présent devis à la somme de',
            'notes' => $quote->notes,
            'terms' => $quote->terms,
        ], 'devis-'.$quote->quote_number);
    }

    /**
     * @return array{content: string, filename: string}
     */
    public function creditNote(CreditNote $creditNote): array
    {
        $creditNote->loadMissing(['company', 'customer', 'invoice', 'lines.taxRate']);
        $isDraft = $creditNote->status === CreditNoteStatus::DRAFT;

        $meta = ['Facture d\'origine' => $creditNote->invoice->invoice_number];
        if ($creditNote->reason) {
            $meta['Motif'] = $creditNote->reason;
        }

        return $this->render('pdf.documents.sales-document', [
            'company' => $creditNote->company,
            'title' => 'FACTURE D\'AVOIR',
            'number' => $creditNote->credit_note_number,
            'date' => $creditNote->credit_note_date,
            'meta' => $meta,
            'isDraft' => $isDraft,
            'partyLabel' => 'Client',
            'party' => $creditNote->customer,
            'lines' => $creditNote->lines,
            'totals' => [
                'Total HT' => Decimal::round((string) $creditNote->subtotal),
                'TVA' => Decimal::round((string) $creditNote->tax_total),
            ],
            'grandTotalLabel' => 'Total TTC',
            'grandTotal' => Decimal::round((string) $creditNote->total),
            'currency' => $creditNote->currency,
            'wordsIntro' => 'Arrêtée la présente facture d\'avoir à la somme de',
            'notes' => $creditNote->notes,
            'terms' => null,
        ], 'avoir-'.($isDraft ? 'brouillon-'.$creditNote->id : $creditNote->credit_note_number));
    }

    /**
     * @return array{content: string, filename: string}
     */
    public function withholdingCertificate(SupplierPayment $payment): array
    {
        if ($payment->status !== SupplierPaymentStatus::POSTED) {
            throw new \InvalidArgumentException('Le certificat de retenue n\'est disponible que pour un règlement comptabilisé.');
        }

        if (bccomp((string) $payment->withholding_amount, '0', 3) <= 0) {
            throw new \InvalidArgumentException('Ce règlement ne comporte pas de retenue à la source.');
        }

        $payment->loadMissing(['company', 'supplier', 'allocations.purchaseInvoice']);

        return $this->render('pdf.documents.withholding-certificate', [
            'payment' => $payment,
            'company' => $payment->company,
            'supplier' => $payment->supplier,
        ], 'certificat-retenue-'.$payment->payment_number);
    }

    /**
     * @param  view-string  $view
     * @param  array<string, mixed>  $data
     * @return array{content: string, filename: string}
     */
    private function render(string $view, array $data, string $baseName): array
    {
        $dompdf = new Dompdf([
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'defaultFont' => 'DejaVu Sans',
        ]);

        $dompdf->setPaper('a4', 'portrait');
        $dompdf->loadHtml(view($view, $data)->render());
        $dompdf->render();

        return [
            'content' => (string) $dompdf->output(),
            'filename' => Str::slug($baseName).'.pdf',
        ];
    }
}
