<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Mail\AccountCreated;
use App\Mail\NewApprenant;
use App\Mail\RegistrationThanks;

$emailTo = 'wulfrid650@gmail.com';

echo "Préparation des emails pour {$emailTo}...\n";

// Fake Model User - Client
$userClient = class_exists('\App\Models\User') ? new class extends \App\Models\User {
    protected $attributes = ['name' => 'Wulfrid Client', 'email' => 'wulfrid650@gmail.com', 'role' => 'client'];
    public function hasRole(string $roleSlug): bool { return $this->role === $roleSlug; }
    public function __get($key) { return $this->attributes[$key] ?? parent::__get($key); }
} : new \stdClass();

$userClient->name = 'Wulfrid Client';
$userClient->email = $emailTo;
$userClient->role = 'client';

// Fake Model User - Apprenant
$userApprenant = class_exists('\App\Models\User') ? new class extends \App\Models\User {
    protected $attributes = ['name' => 'Wulfrid Apprenant', 'email' => 'wulfrid650@gmail.com', 'role' => 'apprenant'];
    public function hasRole(string $roleSlug): bool { return $this->role === $roleSlug; }
    public function __get($key) { return $this->attributes[$key] ?? parent::__get($key); }
} : new \stdClass();

$userApprenant->name = 'Wulfrid Apprenant';
$userApprenant->email = $emailTo;
$userApprenant->role = 'apprenant';

// Fake Model Apprenant
$apprenantMock = new \stdClass();
$apprenantMock->first_name = 'Wulfrid';
$apprenantMock->last_name = 'Apprenant';
$apprenantMock->email = $emailTo;
$apprenantMock->phone = '+221 77 123 45 67';

try {
    echo "1. Email : AccountCreated (Client)\n";
    Mail::to($emailTo)->send(new AccountCreated($userClient, 'ClientPassword123!'));

    echo "2. Email : AccountCreated (Apprenant)\n";
    Mail::to($emailTo)->send(new AccountCreated($userApprenant, 'ApprenantPassword123!'));

    echo "3. Email : NewApprenant (Admin Notification)\n";
    Mail::to($emailTo)->send(new NewApprenant($apprenantMock));

    echo "4. Email : RegistrationThanks (Welcome Apprenant)\n";
    Mail::to($emailTo)->send(new RegistrationThanks($apprenantMock));

    echo "\n✅ Emails Tests envoyés avec succès.\n";
} catch (\Throwable $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
}
