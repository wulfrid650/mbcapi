<?php

// Test email configuration
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Mail;

try {
    Mail::raw('Ceci est un email de test depuis MBC SARL.', function ($message) {
        $message->to('test@aureusprime.com')
                ->subject('Test Configuration Email - MBC SARL');
    });
    
    echo "✅ Email envoyé avec succès via Brevo!\n";
    echo "Configuration email validée.\n";
} catch (Exception $e) {
    echo "❌ Erreur lors de l'envoi de l'email:\n";
    echo $e->getMessage() . "\n";
}
