@extends('emails.layouts.main')

@section('content')
    <h2 style="color: #0B0B0B; margin-top: 0;">Invitation Staff MBC</h2>

    <p>Bonjour {{ $user->name }},</p>

    <p>Un compte staff a été créé pour vous sur la plateforme Madiba Building Construction.</p>

    <div
        style="background-color: #f9fafb; padding: 16px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #C1121F;">
        <p><strong>Vos informations :</strong></p>
        <p>Email : {{ $user->email }}</p>
        <p>ID Employé : {{ $user->employee_id }}</p>
        <p>Rôle : {{ $user->role }}</p>
        <p>Mot de passe temporaire : <strong>{{ $password }}</strong></p>
    </div>

    <p>Pour accéder à votre compte, veuillez vous connecter avec ces identifiants.</p>

    <div style="text-align: center;">
        <a href="{{ $invitationUrl }}" class="btn">Compléter mon profil</a>
    </div>

    <p style="font-size: 12px; color: #6b7280; margin-top: 20px;">Ce lien est valable pour une durée limitée.</p>
@endsection