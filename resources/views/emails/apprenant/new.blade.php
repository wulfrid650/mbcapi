@extends('emails.layouts.main')

@section('content')
    <h2 style="color: #0B0B0B; margin-top: 0;">Nouvelle Inscription Apprenant</h2>

    <p>Bonjour,</p>

    <p>Un nouvel apprenant vient de s'inscrire ou d'être ajouté au système.</p>

    <div style="background-color: #f9fafb; padding: 16px; border-radius: 6px; margin: 20px 0;">
        <p><strong>Informations de l'apprenant :</strong></p>
        <ul style="list-style-type: none; padding: 0;">
            <li>Nom : <strong>{{ $apprenant->first_name }} {{ $apprenant->last_name }}</strong></li>
            <li>Email : {{ $apprenant->email }}</li>
            <li>Téléphone : {{ $apprenant->phone }}</li>
        </ul>
    </div>

    <p>Merci de vérifier son dossier et de lui souhaiter la bienvenue.</p>
@endsection