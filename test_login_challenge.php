<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$c = App\Models\TwoFactorLoginChallenge::latest('id')->first();
if ($c) {
    dump([
        'id' => $c->id,
        'token' => $c->challenge_token,
        'isActive' => $c->isActive(),
        'user_id' => $c->user_id,
        'verified_at' => $c->verified_at,
        'consumed_at' => $c->consumed_at,
    ]);
} else {
    echo 'No challenges found';
}
