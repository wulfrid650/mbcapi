<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ChantierUpdate extends Mailable
{
    use Queueable, SerializesModels;

    public $chantier;
    public $client;
    public $updateNote;

    public function __construct($chantier, $client, $updateNote)
    {
        $this->chantier = $chantier;
        $this->client = $client;
        $this->updateNote = $updateNote;
    }

    public function build()
    {
        return $this->subject('Mise à jour chantier : ' . $this->chantier->name)
            ->view('emails.chantier.update')
            ->bcc('admin@madibabc.com');
    }
}
