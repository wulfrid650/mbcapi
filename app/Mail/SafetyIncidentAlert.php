<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\SafetyIncident;

/**
 * Alerte pour incident de sécurité grave
 */
class SafetyIncidentAlert extends Mailable
{
    use Queueable, SerializesModels;

    public SafetyIncident $incident;

    public function __construct(SafetyIncident $incident)
    {
        $this->incident = $incident;
    }

    public function build()
    {
        $severity = $this->incident->severity_label;
        $project = $this->incident->project?->title ?? 'Inconnu';

        return $this->subject("⚠️ ALERTE SÉCURITÉ [{$severity}] - {$project}")
            ->view('emails.safety.incident_alert')
            ->with([
                'incident' => $this->incident,
                'project' => $this->incident->project,
                'severity' => $severity,
                'type' => $this->incident->type_label,
            ]);
    }
}
