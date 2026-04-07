<?php

namespace App\Services;

use App\Models\FormationEnrollment;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;

class FormationEnrollmentWindowService
{
    public function getWindowMinutes(): int
    {
        return max(1, (int) config('moneroo.pendingEnrollmentWindowMinutes', 60));
    }

    public function getExpiresAt(FormationEnrollment $enrollment): Carbon
    {
        $metadata = $this->getMetadata($enrollment);
        $raw = $metadata['payment_window_expires_at'] ?? null;

        if (is_string($raw) && $raw !== '') {
            return Carbon::parse($raw);
        }

        return ($enrollment->created_at ?? now())->copy()->addMinutes($this->getWindowMinutes());
    }

    public function getRemainingSeconds(FormationEnrollment $enrollment): int
    {
        return max(0, now()->diffInSeconds($this->getExpiresAt($enrollment), false));
    }

    public function reopenPaymentWindow(
        FormationEnrollment $enrollment,
        ?User $actor = null,
        string $reason = 'payment_retry'
    ): FormationEnrollment {
        $expiresAt = now()->addMinutes($this->getWindowMinutes());
        $metadata = array_merge($this->getMetadata($enrollment), [
            'payment_window_started_at' => now()->toISOString(),
            'payment_window_expires_at' => $expiresAt->toISOString(),
            'last_payment_retry_at' => now()->toISOString(),
            'payment_window_reason' => $reason,
            'cancelled_at' => null,
            'cancelled_reason' => null,
        ]);

        if ($actor) {
            $metadata['last_payment_retry_by'] = $actor->id;
            $metadata['last_payment_retry_by_name'] = $actor->name;
        }

        $enrollment->update([
            'status' => 'pending_payment',
            'metadata' => $metadata,
        ]);

        $this->syncPendingPayments($enrollment, [
            'payment_window_expires_at' => $expiresAt->toISOString(),
            'payment_window_reason' => $reason,
            'last_payment_retry_at' => now()->toISOString(),
            'last_payment_retry_by' => $actor?->id,
            'last_payment_retry_by_name' => $actor?->name,
            'initialization_error' => null,
        ]);

        if ($actor) {
            ActivityLogService::log(
                'Paiement repris',
                "{$actor->name} a relance le paiement de l'inscription de {$enrollment->full_name}",
                $enrollment,
                $actor->id
            );
        }

        return $enrollment->fresh(['formation', 'session', 'user']);
    }

    public function expireIfNeeded(FormationEnrollment $enrollment): bool
    {
        if ($enrollment->status !== 'pending_payment') {
            return false;
        }

        $expiresAt = $this->getExpiresAt($enrollment);
        if ($expiresAt->isFuture()) {
            return false;
        }

        $hasCompletedPayment = Payment::query()
            ->where('payable_type', FormationEnrollment::class)
            ->where('payable_id', $enrollment->id)
            ->where('status', 'completed')
            ->exists();

        if ($hasCompletedPayment || $enrollment->paid_at) {
            return false;
        }

        $metadata = array_merge($this->getMetadata($enrollment), [
            'cancelled_at' => now()->toISOString(),
            'cancelled_reason' => 'payment_window_expired',
            'payment_window_expires_at' => $expiresAt->toISOString(),
        ]);

        $enrollment->update([
            'status' => 'cancelled',
            'metadata' => $metadata,
        ]);

        $this->syncPendingPayments($enrollment, [
            'initialization_error' => null,
            'cancelled_at' => now()->toISOString(),
            'cancelled_reason' => 'payment_window_expired',
        ], 'failed');

        ActivityLogService::log(
            'Inscription annulée automatiquement',
            "L'inscription de {$enrollment->full_name} a ete annulee apres expiration du delai de paiement",
            $enrollment
        );

        return true;
    }

    public function expirePendingEnrollments(): int
    {
        $count = 0;

        FormationEnrollment::query()
            ->where('status', 'pending_payment')
            ->orderBy('id')
            ->chunkById(100, function ($enrollments) use (&$count) {
                foreach ($enrollments as $enrollment) {
                    if ($this->expireIfNeeded($enrollment)) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    private function syncPendingPayments(FormationEnrollment $enrollment, array $extraMetadata, string $status = 'pending'): void
    {
        Payment::query()
            ->where('payable_type', FormationEnrollment::class)
            ->where('payable_id', $enrollment->id)
            ->where('status', 'pending')
            ->get()
            ->each(function (Payment $payment) use ($extraMetadata, $status) {
                $paymentMetadata = array_merge(
                    is_array($payment->metadata) ? $payment->metadata : [],
                    array_filter($extraMetadata, static fn ($value) => $value !== null)
                );

                $payment->update([
                    'status' => $status,
                    'metadata' => $paymentMetadata,
                ]);
            });
    }

    private function getMetadata(FormationEnrollment $enrollment): array
    {
        return is_array($enrollment->metadata) ? $enrollment->metadata : [];
    }
}
