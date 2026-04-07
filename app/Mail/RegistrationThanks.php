<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationThanks extends Mailable
{
    use Queueable, SerializesModels;

    public $apprenant;

    public function __construct($apprenant)
    {
        $this->apprenant = $apprenant;
    }

    public function build()
    {
        return $this->subject('Bienvenue chez Madiba Building Corp !')
            ->view('emails.apprenant.thanks')
            ->bcc('admin@madibabc.com');
    }
}
