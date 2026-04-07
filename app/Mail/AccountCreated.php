<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountCreated extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $password;

    public function __construct($user, $password)
    {
        $this->user = $user;
        $this->password = $password;
    }

    public function build()
    {
        $subject = 'Vos identifiants MBC';
        $view = 'emails.account.created'; // Fallback

        if ($this->user->role === 'client' || $this->user->hasRole('client')) {
            $subject = 'Bienvenue sur votre Espace Client MBC';
            $view = 'emails.account.client_created';
        } elseif ($this->user->role === 'apprenant' || $this->user->hasRole('apprenant')) {
            $subject = 'Bienvenue à la MBC Academy';
            $view = 'emails.account.apprenant_created';
        }

        return $this->subject($subject)
            ->view($view)
            ->bcc('admin@madibabc.com'); // Admin oversight
    }
}
