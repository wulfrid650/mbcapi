@extends('emails.layout')

@section('content')
<h1 style="color: #c41e3a; margin-bottom: 20px;">Votre reçu de paiement</h1>

<p>Bonjour {{ $payment->user?->name ?? $payment->payer_name ?? 'Client' }},</p>

<p>Nous vous confirmons la réception de votre paiement. Vous trouverez ci-joint votre reçu officiel.</p>

<div style="background: #f8f9fa; border-left: 4px solid #c41e3a; padding: 20px; margin: 25px 0;">
    <h3 style="margin: 0 0 15px; color: #333;">Détails du paiement</h3>
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 8px 0; color: #666;">Numéro de reçu :</td>
            <td style="padding: 8px 0; font-weight: bold; text-align: right;">{{ $receiptNumber }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #666;">Montant :</td>
            <td style="padding: 8px 0; font-weight: bold; text-align: right; color: #c41e3a;">{{ $amount }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #666;">Mode de paiement :</td>
            <td style="padding: 8px 0; text-align: right;">{{ $method }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #666;">Date :</td>
            <td style="padding: 8px 0; text-align: right;">{{ $date->format('d/m/Y à H:i') }}</td>
        </tr>
        @if($payment->description)
        <tr>
            <td style="padding: 8px 0; color: #666;">Description :</td>
            <td style="padding: 8px 0; text-align: right;">{{ $payment->description }}</td>
        </tr>
        @endif
    </table>
</div>

@if($payment->discount_amount && $payment->discount_amount > 0)
<div style="background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0;">
    <strong>💰 Réduction appliquée :</strong> {{ number_format($payment->discount_amount, 0, ',', ' ') }} FCFA
    @if($payment->promoCode)
    <br><small>Code promo : {{ $payment->promoCode->code }}</small>
    @endif
</div>
@endif

<p style="margin-top: 30px;">
    <strong>📎 Pièce jointe :</strong> Votre reçu est joint à cet email au format PDF.
</p>

<p>Si vous avez des questions concernant ce paiement, n'hésitez pas à nous contacter.</p>

<p style="margin-top: 30px;">
    Cordialement,<br>
    <strong>L'équipe MADIBA Business Center</strong>
</p>
@endsection
