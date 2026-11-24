<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sends a sales document (invoice, quote, credit note) as a PDF attachment.
 */
class SalesDocumentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $documentLabel,
        public string $documentNumber,
        public string $companyName,
        public string $customerName,
        public string $totalLabel,
        public string $pdfContent,
        public string $pdfFilename,
        public ?string $replyToAddress = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->documentLabel} {$this->documentNumber} — {$this->companyName}",
            replyTo: $this->replyToAddress !== null ? [$this->replyToAddress] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.sales-document');
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->pdfContent, $this->pdfFilename)->withMime('application/pdf'),
        ];
    }
}
