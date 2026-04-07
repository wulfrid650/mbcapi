@extends('emails.layouts.main')

@section('content')
    <h2 style="color: #0B0B0B; margin-top: 0;">Nouvelle Demande de Devis</h2>

    <p>Bonjour,</p>

    <p>Une nouvelle demande de devis a été soumise sur le site web.</p>

    <div style="background-color: #f9fafb; padding: 16px; border-radius: 6px; margin: 20px 0;">
        <p><strong>Détails du prospect :</strong></p>
        <ul style="list-style-type: none; padding: 0;">
            <li>Nom : <strong>{{ $quote->name }}</strong></li>
            <li>Email : {{ $quote->email }}</li>
            <li>Téléphone : {{ $quote->phone }}</li>
            <li>Projet : {{ $quote->project_type }}</li>
        </ul>
        <p><strong>Description :</strong></p>
        <p><em>"{{ $quote->description }}"</em></p>
    </div>

    <p>Merci de traiter cette demande dans les plus brefs délais.</p>
@endsection