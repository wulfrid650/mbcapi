<?php

namespace App\Observers;

use App\Models\Payment;
use App\Services\ActivityLogService;

class PaymentObserver
{
    /**
     * Handle the Payment "created" event.
     */
    public function created(Payment $payment): void
    {
        if ($payment->status === 'completed') {
            ActivityLogService::logPaymentCompleted($payment);
        } elseif ($payment->status === 'pending') {
            ActivityLogService::logPaymentPending($payment);
        }
    }

    /**
     * Handle the Payment "updated" event.
     */
    public function updated(Payment $payment): void
    {
        // Log status changes
        if ($payment->isDirty('status')) {
            $oldStatus = $payment->getOriginal('status');
            $newStatus = $payment->status;

            if ($newStatus === 'completed') {
                ActivityLogService::logPaymentCompleted($payment);
            } elseif ($newStatus === 'failed') {
                ActivityLogService::logPaymentFailed($payment);
            }
        }
    }

    /**
     * Handle the Payment "deleted" event.
     */
    public function deleted(Payment $payment): void
    {
        ActivityLogService::logPaymentDeleted($payment, auth()->user());
    }
}
