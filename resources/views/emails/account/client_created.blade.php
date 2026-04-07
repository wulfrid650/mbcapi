@extends('emails.layouts.main')

@section('content')
    <h2 style="color: #0B0B0B; margin-top: 0;">Bienvenue chez MBC !</h2>

    <p>Bonjour {{ $user->name }},</p>

    <p>Nous sommes ravis de vous compter parmi nos clients privilégiés.</p>
    <p>Votre espace client sécurisé a été créé sur la plateforme Madiba Building Corporation. Il vous permettra de suivre
        l'avancement de vos projets, consulter vos devis et factures, et communiquer avec nos équipes.</p>

    <div
        style="background-color: #f9fafb; padding: 16px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #C1121F;">
        <p><strong>Vos identifiants de connexion :</strong></p>
        <p>Email : {{ $user->email }}</p>
        <p>Mot de passe temporaire : <strong>{{ $password }}</strong></p>
    </div>

    <p>Pour des raisons de sécurité, nous vous invitons à modifier ce mot de passe dès votre première connexion.</p>

    <div style="text-align: center;">
        <a href="https://madibabc.com/connexion" class="btn">Accéder à mon Espace Client</a>
    </div>

    <p style="margin-top: 30px;">Nous vous remercions de votre confiance.</p>
@endsection