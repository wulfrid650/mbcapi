<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Email de reçu de paiement avec pièce jointe PDF
 */
class PaymentReceiptWithAttachment extends Mailable
{
    use Queueable, SerializesModels;

    public $payment;
    public $receiptPath;

    public function __construct($payment, string $receiptPath)
    {
        $this->payment = $payment;
        $this->receiptPath = $receiptPath;
    }

    public function build()
    {
        $subject = 'Votre reçu de paiement - ' . $this->payment->receipt_number;
        
        $mail = $this->subject($subject)
            ->view('emails.payment.receipt_with_attachment')
            ->with([
                'payment' => $this->payment,
                'receiptNumber' => $this->payment->receipt_number,
                'amount' => number_format($this->payment->amount, 0, ',', ' ') . ' FCFA',
                'method' => $this->payment->method_label,
                'date' => $this->payment->validated_at ?? $this->payment->created_at,
            ]);

        // Joindre le PDF
        if ($this->receiptPath && Storage::disk('public')->exists($this->receiptPath)) {
            $mail->attach(Storage::disk('public')->path($this->receiptPath), [
                'as' => 'Recu-' . $this->payment->receipt_number . '.pdf',
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }
}
