<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;

class QuoteResponse extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public object $quoteResponse)
    {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Réponse à votre demande de devis - ' . ($this->quoteResponse->quote_number ?? 'MBC'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.quote-response',
            with: [
                'name' => $this->quoteResponse->name,
                'quoteNumber' => $this->quoteResponse->quote_number ?? 'N/A',
                'responseMessage' => $this->quoteResponse->response_message,
                'hasDocument' => $this->quoteResponse->has_document ?? false,
                'documentUrl' => $this->quoteResponse->document_url ?? null,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
