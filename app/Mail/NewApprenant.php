<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewApprenant extends Mailable
{
    use Queueable, SerializesModels;

    public $apprenant;

    public function __construct($apprenant)
    {
        $this->apprenant = $apprenant;
    }

    public function build()
    {
        return $this->subject('Nouvel Apprenant Inscrit : ' . $this->apprenant->last_name)
            ->view('emails.apprenant.new')
            ->bcc('admin@madibabc.com');
    }
}
