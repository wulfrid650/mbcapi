@extends('emails.layouts.main')

@section('content')
    <h2 style="color: #0B0B0B; margin-top: 0;">Bienvenue à la MBC Academy !</h2>

    <p>Bonjour {{ $user->name }},</p>

    <p>Félicitations pour votre inscription ! Votre aventure d'apprentissage commence maintenant.</p>
    <p>Votre compte apprenant a été créé avec succès. Vous pouvez désormais accéder à votre espace personnel pour retrouver
        vos cours, vos évaluations et vos certificats.</p>

    <div
        style="background-color: #f9fafb; padding: 16px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #C1121F;">
        <p><strong>Vos identifiants de connexion :</strong></p>
        <p>Email : {{ $user->email }}</p>
        <p>Mot de passe temporaire : <strong>{{ $password }}</strong></p>
    </div>

    <p>N'oubliez pas de changer votre mot de passe une fois connecté.</p>

    <div style="text-align: center;">
        <a href="https://madibabc.com/connexion" class="btn">Commencer mon apprentissage</a>
    </div>

    <p style="margin-top: 30px;">Bon courage pour votre formation !</p>
@endsection