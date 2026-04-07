@extends('emails.layouts.main')

@section('content')
    <h2 style="color: #0B0B0B; margin-top: 0;">Paiement Validé</h2>

    <p>Bonjour {{ $payment->user->name }},</p>

    <p>Nous vous confirmons la bonne réception de votre paiement concernant le projet :
        <strong>{{ $payment->description }}</strong>.</p>

    <div
        style="background-color: #f9fafb; padding: 16px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #C1121F;">
        <p><strong>Détails de la transaction :</strong></p>
        <ul style="list-style-type: none; padding: 0;">
            <li>Référence : <strong>{{ $payment->reference }}</strong></li>
            <li>Montant : <strong>{{ number_format($payment->amount, 0, ',', ' ') }} {{ $payment->currency }}</strong></li>
            <li>Date :
                {{ $payment->validated_at ? \Carbon\Carbon::parse($payment->validated_at)->format('d/m/Y') : date('d/m/Y') }}
            </li>
            <li>Moyen de paiement : {{ ucfirst(str_replace('_', ' ', $payment->method ?? 'N/A')) }}</li>
        </ul>
    </div>

    <p>Une facture acquittée est disponible dans votre espace client.</p>

    <div style="text-align: center;">
        <a href="https://madibabc.com/client/factures" class="btn">Voir ma facture</a>
    </div>
@endsection