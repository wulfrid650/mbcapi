<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Mail\LoginTwoFactorCode;
use App\Mail\QuoteResponse;
use App\Mail\StaffInvitation;

$emailTo = 'wulfrid650@gmail.com';

try {
    echo "Préparation des emails pour {$emailTo}...\n";

    // Fake user
    $user = new User();
    $user->name = 'Wulfrid';
    $user->email = $emailTo;
    $user->role = 'admin';
    $user->employee_id = 'MBC-001';

    // 1. Email : OTP (Auth)
    echo "1. Envoi du code OTP...\n";
    Mail::to($emailTo)->send(new LoginTwoFactorCode($user, '123456', now()->addMinutes(10)->toDateTimeString()));

    // 2. Email : Quote Response (Le template refait)
    echo "2. Envoi de la réponse au devis...\n";
    $quoteMock = new \stdClass();
    $quoteMock->name = 'Wulfrid';
    $quoteMock->quote_number = 'DEV-2026-001';
    $quoteMock->response_message = "Merci pour l'intérêt que vous portez à MBC.\nVoici les détails de votre devis de construction.";
    $quoteMock->has_document = true;
    $quoteMock->document_url = 'https://madibabc.com/document-temporaire.pdf';

    Mail::to($emailTo)->send(new QuoteResponse($quoteMock));

    // 3. Email : Staff Invitation
    echo "3. Envoi de l'invitation d'équipe...\n";
    Mail::to($emailTo)->send(new StaffInvitation($user, 'Secr3tPassword!', 'dummy-token'));

    echo "\n✅ Tous les e-mails de test ont été envoyés avec succès à {$emailTo}!\n";

} catch (Throwable $e) {
    echo "❌ Erreur lors de l'envoi de l'email:\n";
    echo $e->getMessage() . "\n";
}

