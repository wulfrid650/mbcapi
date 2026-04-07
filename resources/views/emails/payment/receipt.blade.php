@extends('emails.layouts.main')

@section('content')
    <h2 style="color: #0B0B0B; margin-top: 0;">Paiement Validé</h2>
    
    <p>Bonjour {{ $payment->user->first_name }},</p>
    
    <p>Nous vous confirmons la réception et la validation de votre paiement.</p>
    
    <div style="background-color: #f9fafb; padding: 16px; border-radius: 6px; margin: 20px 0;">
        <p><strong>Détails du paiement :</strong></p>
        <ul style="list-style-type: none; padding: 0;">
            <li>Référence : <strong>{{ $payment->reference }}</strong></li>
            <li>Montant : <strong>{{ number_format($payment->amount, 0, ',', ' ') }} FCFA</strong></li>
            <li>Motif : {{ $payment->reason }}</li>
            <li>Date : {{ $payment->validated_at ? \Carbon\Carbon::parse($payment->validated_at)->format('d/m/Y') : date('d/m/Y') }}</li>
        </ul>
    </div>
    
    <p>Vous pouvez télécharger votre reçu officiel directement depuis votre espace personnel.</p>
    
    <div style="text-align: center;">
        <a href="https://madibabc.com/connexion" class="btn">Voir mes reçus</a>
    </div>
@endsection
