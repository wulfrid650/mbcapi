<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Mail;

try {
    echo "Sending test mail to no-reply@madibabc.com\n";
    Mail::raw('Test 2FA Email', function ($message) {
        $message->to('no-reply@madibabc.com')->subject('2FA Mail Test');
    });
    echo "Mail successfully sent from script!\n";
} catch (\Throwable $e) {
    echo "MAIL FAIL: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
