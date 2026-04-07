@extends('emails.layouts.main')

@section('content')
    <h2 style="color: #C1121F; margin-top: 0;">✅ Réponse à votre demande de devis</h2>

    <p>Bonjour {{ $name }},</p>

    <p>Nous avons bien reçu votre demande de devis et nous vous remercions de votre intérêt pour nos services.</p>

    @if($quoteNumber)
    <div class="info-box">
        <p style="margin: 0; font-size: 12px; text-transform: uppercase; color: #6b7280; font-weight: 600;">Numéro de devis</p>
        <p style="margin: 5px 0 0; font-size: 16px; font-weight: bold; color: #1f2937;">{{ $quoteNumber }}</p>
    </div>
    @endif

    <div class="divider"></div>

    <h3 style="color: #374151; margin-top: 25px;">📋 Notre réponse :</h3>
    
    <div style="background-color: #f9fafb; border: 1px solid #e5e7eb; padding: 20px; border-radius: 6px; margin: 20px 0;">
        {!! nl2br(e($responseMessage)) !!}
    </div>

    @if($hasDocument && $documentUrl)
    <div style="text-align: center; margin: 30px 0;">
        <p style="margin-bottom: 15px; color: #6b7280;">
            Nous avons joint un document détaillé à cette réponse :
        </p>
        <a href="{{ $documentUrl }}" class="btn">
            📄 Télécharger le document
        </a>
    </div>
    @endif

    <div class="divider"></div>

    <p style="color: #6b7280; font-size: 14px;">
        <strong>Besoin d'informations complémentaires ?</strong><br>
        N'hésitez pas à nous contacter directement. Notre équipe reste à votre disposition pour répondre à toutes vos questions.
    </p>

    <p style="margin-top: 25px;">
        Cordialement,<br>
        <strong>L'équipe MBC</strong>
    </p>
@endsection
