@extends('emails.layouts.main')

@section('content')
    <h2 style="color: #C1121F; font-size: 22px; font-weight: 700; margin-top: 0; margin-bottom: 20px;">
        🔔 Nouvelle connexion détectée
    </h2>

    <p>Bonjour <strong>{{ $user->name }}</strong>,</p>

    <p>
        Nous avons détecté une connexion à votre compte depuis un <strong>appareil ou une localisation que nous ne reconnaissons pas</strong>.
    </p>

    {{-- Info box de détails --}}
    <div style="background-color: #fff1f2; border-left: 4px solid #C1121F; padding: 16px 20px; margin: 20px 0; border-radius: 4px;">
        <p style="margin: 0 0 8px; font-size: 14px; font-weight: 600; color: #374151;">📍 Détails de la connexion :</p>
        <table style="width: 100%; font-size: 14px; color: #4b5563; border-collapse: collapse;">
            <tr>
                <td style="padding: 4px 0; width: 40%;"><strong>Pays :</strong></td>
                <td style="padding: 4px 0;">{{ $loginHistory->country ?? 'Inconnu' }}</td>
            </tr>
            <tr>
                <td style="padding: 4px 0;"><strong>Ville :</strong></td>
                <td style="padding: 4px 0;">{{ $loginHistory->city ?? 'Inconnu' }}</td>
            </tr>
            <tr>
                <td style="padding: 4px 0;"><strong>Adresse IP :</strong></td>
                <td style="padding: 4px 0;">{{ $loginHistory->ip_address ?? 'Inconnue' }}</td>
            </tr>
            <tr>
                <td style="padding: 4px 0;"><strong>Fournisseur :</strong></td>
                <td style="padding: 4px 0;">{{ $loginHistory->isp ?? 'Inconnu' }}</td>
            </tr>
            <tr>
                <td style="padding: 4px 0;"><strong>Date et heure :</strong></td>
                <td style="padding: 4px 0;">{{ $loginHistory->created_at->format('d/m/Y à H:i:s') }} (UTC)</td>
            </tr>
        </table>
    </div>

    {{-- Si c'est l'utilisateur lui-même --}}
    <div style="background-color: #f0fdf4; border-left: 4px solid #22c55e; padding: 16px 20px; margin: 20px 0; border-radius: 4px;">
        <p style="margin: 0; font-size: 14px; color: #166534;">
            ✅ <strong>C'est vous ?</strong> Aucune action n'est requise. Vous pouvez ignorer cet email.
        </p>
    </div>

    {{-- Si ce n'est pas l'utilisateur --}}
    <div style="background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 16px 20px; margin: 20px 0; border-radius: 4px;">
        <p style="margin: 0 0 8px; font-size: 14px; color: #92400e;">
            ⚠️ <strong>Ce n'est pas vous ?</strong> Votre compte pourrait être compromis.
        </p>
        <p style="margin: 0; font-size: 14px; color: #92400e;">
            Modifiez immédiatement votre mot de passe et contactez notre support.
        </p>
    </div>

    <p style="margin-top: 24px; font-size: 14px; color: #6b7280;">
        Pour votre sécurité, nous vous recommandons de ne jamais partager vos identifiants de connexion.
    </p>
@endsection
