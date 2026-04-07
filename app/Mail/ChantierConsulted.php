<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ChantierConsulted extends Mailable
{
    use Queueable, SerializesModels;

    public $chantier;
    public $client;

    public function __construct($chantier, $client)
    {
        $this->chantier = $chantier;
        $this->client = $client;
    }

    public function build()
    {
        return $this->subject('Client a consulté le chantier : ' . $this->chantier->name)
            ->view('emails.chantier.consulted')
            ->bcc('admin@madibabc.com');
    }
}
