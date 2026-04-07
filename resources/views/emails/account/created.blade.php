@extends('emails.layouts.main')

@section('content')
    <h2 style="color: #0B0B0B; margin-top: 0;">Bienvenue chez MBC !</h2>

    <p>Bonjour {{ $user->first_name }},</p>

    <p>Votre compte a été créé avec succès sur la plateforme Madiba Building Construction.</p>

    <div
        style="background-color: #f9fafb; padding: 16px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #C1121F;">
        <p><strong>Vos identifiants de connexion :</strong></p>
        <p>Email : {{ $user->email }}</p>
        <p>Mot de passe : {{ $password }}</p>
    </div>

    <p>Nous vous remercions de nous avoir choisi.</p>

    <div style="text-align: center;">
        <a href="https://madibabc.com/connexion" class="btn">Accéder à mon compte</a>
    </div>
@endsection