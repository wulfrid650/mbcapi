@extends('emails.layouts.main')

@section('content')
    <h2 style="color: #0B0B0B; margin-top: 0;">Mise à jour de chantier</h2>

    <p>Bonjour {{ $client->first_name }},</p>

    <p>Une nouvelle mise à jour est disponible pour le chantier : <strong>{{ $chantier->name }}</strong>.</p>

    <div style="background-color: #f9fafb; padding: 16px; border-radius: 6px; margin: 20px 0;">
        <p><strong>Note du Chef de Chantier :</strong></p>
        <p style="font-style: italic;">"{{ $updateNote }}"</p>
    </div>

    <p>Vous pouvez consulter les détails et l'avancement complet en cliquant sur le bouton ci-dessous.</p>

    <div style="text-align: center;">
        <a href="https://madibabc.com/track-work/{{ $chantier->id }}" class="btn">Voir l'avancement</a>
    </div>
@endsection