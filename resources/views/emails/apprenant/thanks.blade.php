@extends('emails.layouts.main')

@section('content')
    <h2 style="color: #0B0B0B; margin-top: 0;">Félicitations {{ $apprenant->first_name }} !</h2>

    <p>Merci de vous être inscrit à une formation chez <strong>Madiba Building Construction</strong>.</p>

    <p>Nous sommes ravis de vous compter parmi nos apprenants. Votre parcours vers l'excellence commence aujourd'hui.</p>

    <p>Si vous avez des questions, n'hésitez pas à contacter notre secrétariat ou votre formateur référent.</p>

    <div style="text-align: center;">
        <a href="https://madibabc.com/apprenant/dashboard" class="btn">Accéder à mon espace</a>
    </div>
@endsection