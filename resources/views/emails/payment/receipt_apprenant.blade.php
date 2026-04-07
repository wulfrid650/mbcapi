@extends('emails.layouts.main')

@section('content')
    <h2 style="color: #0B0B0B; margin-top: 0;">Paiement Reçu !</h2>
    
    <p>Bonjour {{ $payment->user->name }},</p>
    
    <p>Merci pour votre paiement. Votre inscription/formation est à jour !</p>
    <p>Motif : <strong>{{ $payment->description }}</strong></p>
    
    <div style="background-color: #f9fafb; padding: 16px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #C1121F;">
        <p><strong>Récapitulatif :</strong></p>
        <ul style="list-style-type: none; padding: 0;">
            <li>Référence : <strong>{{ $payment->reference }}</strong></li>
            <li>Montant réglé : <strong>{{ number_format($payment->amount, 0, ',', ' ') }} {{ $payment->currency }}</strong></li>
            <li>Date : {{ $payment->validated_at ? \Carbon\Carbon::parse($payment->validated_at)->format('d/m/Y') : date('d/m/Y') }}</li>
        </ul>
    </div>
    
    <p>Vous pouvez retrouver l'historique de vos paiements et télécharger vos reçus dans votre espace étudiant.</p>
    
    <div style="text-align: center;">
        <a href="https://madibabc.com/apprenant/recus" class="btn">Mes reçus</a>
    </div>
@endsection
