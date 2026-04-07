<?php

// Test email layout with logo
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;

try {
    // Test helpers
    echo "🔍 Testing email helpers:\n";
    echo "Logo: " . (email_logo() ?? 'Pas de logo') . "\n";
    echo "Company: " . email_company_name() . "\n";
    echo "Site: " . email_site_name() . "\n";
    echo "Tagline: " . email_tagline() . "\n";
    $contact = email_contact_info();
    echo "Email: " . $contact['email'] . "\n";
    echo "Phone: " . $contact['phone'] . "\n";
    echo "Address: " . $contact['address'] . "\n\n";
    
    // Test sending email with new layout
    echo "📧 Envoi d'un email de test avec le nouveau layout...\n";
    
    Mail::send('emails.layout', [], function ($message) {
        $message->to('test@aureusprime.com')
                ->subject('Test Layout Email - MBC SARL');
    });
    
    echo "✅ Email de test envoyé avec succès!\n";
    echo "✅ Le layout inclut maintenant le logo de l'entreprise\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
