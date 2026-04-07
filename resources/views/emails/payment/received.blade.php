@extends('emails.layouts.main')

@section('content')
    <h2 style="color: #0B0B0B; margin-top: 0;">Paiement Reçu</h2>

    <p>Bonjour,</p>

    <p>Un nouveau paiement a été validé dans le système.</p>

    <div style="background-color: #f9fafb; padding: 16px; border-radius: 6px; margin: 20px 0;">
        <p><strong>Détails du paiement :</strong></p>
        <ul style="list-style-type: none; padding: 0;">
            <li>Client/Apprenant : <strong>{{ $payment->user->first_name }} {{ $payment->user->last_name }}</strong></li>
            <li>Montant : <strong>{{ number_format($payment->amount, 0, ',', ' ') }} FCFA</strong></li>
            <li>Motif : {{ $payment->reason }}</li>
            <li>Référence : {{ $payment->reference }}</li>
        </ul>
    </div>

    <div style="text-align: center;">
        <a href="https://madibabc.com/secretaire/recus" class="btn">Gérer les reçus</a>
    </div>
@endsection