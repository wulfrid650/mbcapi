@extends('emails.layouts.main')

@section('content')
    <h2 style="color: #0B0B0B; margin-top: 0;">Mot de passe oublié ?</h2>

    <p>Bonjour {{ $user->name }},</p>

    <p>Vous recevez cet email car nous avons reçu une demande de réinitialisation de mot de passe pour votre compte.</p>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $resetUrl }}" class="btn">Réinitialiser mon mot de passe</a>
    </div>

    <p>Ce lien de réinitialisation expirera dans 60 minutes.</p>

    <p>Si vous n'avez pas demandé de réinitialisation de mot de passe, aucune autre action n'est requise.</p>

    <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">

    <p style="font-size: 12px; color: #6b7280;">Si vous rencontrez des difficultés pour cliquer sur le bouton "Réinitialiser
        mon mot de passe", copiez et collez l'URL ci-dessous dans votre navigateur web :</p>
    <p style="font-size: 12px; color: #6b7280; word-break: break-all;">{{ $resetUrl }}</p>
@endsection