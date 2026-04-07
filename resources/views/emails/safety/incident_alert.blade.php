@extends('emails.layout')

@section('content')
<div style="background: #dc3545; color: white; padding: 20px; margin-bottom: 20px; border-radius: 5px;">
    <h1 style="margin: 0; font-size: 24px;">⚠️ ALERTE INCIDENT DE SÉCURITÉ</h1>
    <p style="margin: 10px 0 0; font-size: 16px;">Niveau de gravité : <strong>{{ strtoupper($severity) }}</strong></p>
</div>

<div style="background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin-bottom: 20px;">
    <h2 style="margin: 0 0 10px; color: #721c24;">{{ $incident->title }}</h2>
    <p style="margin: 0; color: #721c24;">
        <strong>Type :</strong> {{ $type }}<br>
        <strong>Date :</strong> {{ $incident->date->format('d/m/Y') }}
        @if($incident->time)
        à {{ $incident->time->format('H:i') }}
        @endif
    </p>
</div>

<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
    <tr>
        <td style="padding: 10px; background: #f5f5f5; font-weight: bold; width: 30%;">Chantier</td>
        <td style="padding: 10px; border-bottom: 1px solid #eee;">{{ $project->title ?? 'Non spécifié' }}</td>
    </tr>
    <tr>
        <td style="padding: 10px; background: #f5f5f5; font-weight: bold;">Localisation</td>
        <td style="padding: 10px; border-bottom: 1px solid #eee;">{{ $incident->location ?? $project->location ?? 'Non spécifié' }}</td>
    </tr>
    <tr>
        <td style="padding: 10px; background: #f5f5f5; font-weight: bold;">Signalé par</td>
        <td style="padding: 10px; border-bottom: 1px solid #eee;">{{ $incident->reporter?->name ?? 'Non spécifié' }}</td>
    </tr>
</table>

<h3 style="color: #333; border-bottom: 2px solid #c41e3a; padding-bottom: 10px;">Description de l'incident</h3>
<p style="background: #fff; padding: 15px; border: 1px solid #eee; border-radius: 5px;">
    {{ $incident->description }}
</p>

@if($incident->persons_involved && count($incident->persons_involved) > 0)
<h3 style="color: #333;">Personnes impliquées</h3>
<ul style="background: #fff3cd; padding: 15px 15px 15px 35px; border-radius: 5px;">
    @foreach($incident->persons_involved as $person)
    <li>{{ $person }}</li>
    @endforeach
</ul>
@endif

@if($incident->injuries && count($incident->injuries) > 0)
<h3 style="color: #dc3545;">Blessures signalées</h3>
<ul style="background: #f8d7da; padding: 15px 15px 15px 35px; border-radius: 5px;">
    @foreach($incident->injuries as $injury)
    <li>{{ $injury }}</li>
    @endforeach
</ul>
@endif

@if($incident->actions_taken)
<h3 style="color: #28a745;">Actions immédiates prises</h3>
<p style="background: #d4edda; padding: 15px; border-radius: 5px;">
    {{ $incident->actions_taken }}
</p>
@endif

<div style="background: #c41e3a; color: white; padding: 15px; margin-top: 30px; border-radius: 5px; text-align: center;">
    <p style="margin: 0; font-weight: bold;">Action requise immédiatement</p>
    <p style="margin: 10px 0 0; font-size: 14px;">Veuillez traiter cet incident dans les plus brefs délais.</p>
</div>

<p style="margin-top: 30px; color: #666; font-size: 12px; text-align: center;">
    Cet email a été généré automatiquement par le système de gestion des chantiers MBC.<br>
    Référence incident : #{{ $incident->id }} | {{ now()->format('d/m/Y H:i') }}
</p>
@endsection
