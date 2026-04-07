@extends('emails.layouts.main')

@section('content')
    <h2 style="color: #0B0B0B; margin-top: 0;">Consultation Chantier</h2>

    <p>Bonjour Chef Chantier,</p>

    <p>Le client <strong>{{ $client->first_name }} {{ $client->last_name }}</strong> vient de consulter les détails du
        chantier :</p>

    <h3 style="color: #C1121F;">{{ $chantier->name }}</h3>

    <p>C'est un bon moment pour s'assurer que toutes les informations sont à jour !</p>

    <div style="text-align: center;">
        <a href="https://madibabc.com/gerer-chantier/{{ $chantier->id }}" class="btn">Gérer le chantier</a>
    </div>
@endsection