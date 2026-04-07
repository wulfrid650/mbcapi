<?php

// Check site settings in database
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\SiteSetting;

echo "📊 Valeurs actuelles dans la base de données:\n\n";

$keys = [
    'company_slogan',
    'email', 
    'phone',
    'address',
    'company_logo',
    'company_name',
    'phone_secondary',
    'address_full'
];

foreach ($keys as $key) {
    $value = SiteSetting::where('key', $key)->value('value');
    echo "$key: " . ($value ?? 'NON DÉFINI') . "\n";
}

echo "\n📝 Pour mettre à jour ces valeurs:\n";
echo "Allez dans l'interface admin → Paramètres → Site\n";
