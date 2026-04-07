<?php

use App\Models\Payment;
use App\Services\ReceiptService;
use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$service = new ReceiptService();

// Find some payments without receipts
$payments = Payment::whereNull('receipt_number')->limit(3)->get();

if ($payments->isEmpty()) {
    echo "No payments without receipt_number found to test.\n";
    exit;
}

echo "Found " . $payments->count() . " payments to process.\n";

foreach ($payments as $payment) {
    try {
        echo "Processing Payment ID: " . $payment->id . "...\n";
        $result = $service->generateReceipt($payment);
        echo "Success! Number: " . $result['receipt_number'] . "\n";
    } catch (\Exception $e) {
        echo "FAILED for ID " . $payment->id . ": " . $e->getMessage() . "\n";
    }
}
