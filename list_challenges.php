<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\TwoFactorLoginChallenge;
use Carbon\Carbon;

$challenges = TwoFactorLoginChallenge::all();
echo "Total challenges: " . $challenges->count() . "\n";
foreach ($challenges as $c) {
    echo "ID: {$c->id}, Token: {$c->challenge_token}, User ID: {$c->user_id}, Active: " . ($c->isActive() ? 'YES' : 'NO') . "\n";
    echo "  Expires at: " . $c->expires_at?->toIso8601String() . "\n";
    echo "  Now: " . Carbon::now()->toIso8601String() . "\n";
    echo "  Verified at: " . ($c->verified_at ? $c->verified_at->toIso8601String() : 'NULL') . "\n";
    echo "  Consumed at: " . ($c->consumed_at ? $c->consumed_at->toIso8601String() : 'NULL') . "\n";
    echo "-------------------\n";
}
