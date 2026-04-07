<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Payment;

class PaymentReceipt extends Mailable
{
    use Queueable, SerializesModels;

    public Payment $payment;

    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
    }

    public function build()
    {
        $subject = 'Reçu de Paiement';
        $view = 'emails.payment.receipt';

        $user = method_exists($this->payment, 'deriveUser')
            ? $this->payment->deriveUser()
            : $this->payment->user;

        if ($user) {
            if ($user->role === 'client' || $user->hasRole('client')) {
                $subject = 'Confirmation de Paiement - MBC';
                $view = 'emails.payment.receipt_client';
            } elseif ($user->role === 'apprenant' || $user->hasRole('apprenant')) {
                $subject = 'Paiement effectué avec succès';
                $view = 'emails.payment.receipt_apprenant';
            }
        }

        return $this->subject($subject)
            ->view($view)
            ->bcc('admin@madibabc.com'); // Admin oversight
    }
}
