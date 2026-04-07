<?php

namespace App\Services;

use App\Models\FormationEnrollment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EnrollmentOwnershipService
{
    public function attachByEmail(User $user): array
    {
        $email = mb_strtolower(trim((string) $user->email));
        if ($email === '') {
            return [
                'enrollments' => 0,
                'payments' => 0,
            ];
        }

        return DB::transaction(function () use ($user, $email): array {
            $attachedSessionIds = array_fill_keys(
                FormationEnrollment::query()
                    ->where('user_id', $user->id)
                    ->whereNotNull('session_id')
                    ->lockForUpdate()
                    ->pluck('session_id')
                    ->map(fn ($sessionId) => (int) $sessionId)
                    ->all(),
                true
            );

            $orphanEnrollments = FormationEnrollment::query()
                ->whereNull('user_id')
                ->whereRaw('LOWER(email) = ?', [$email])
                ->orderByRaw("
                    CASE status
                        WHEN 'completed' THEN 0
                        WHEN 'confirmed' THEN 1
                        WHEN 'pending_payment' THEN 2
                        WHEN 'cancelled' THEN 3
                        ELSE 4
                    END
                ")
                ->orderByDesc('completed_at')
                ->orderByDesc('paid_at')
                ->orderByDesc('created_at')
                ->lockForUpdate()
                ->get();

            $attachedEnrollments = 0;

            foreach ($orphanEnrollments as $enrollment) {
                $sessionId = $enrollment->session_id ? (int) $enrollment->session_id : null;

                if ($sessionId !== null && isset($attachedSessionIds[$sessionId])) {
                    continue;
                }

                $enrollment->forceFill(['user_id' => $user->id])->save();
                $attachedEnrollments++;

                if ($sessionId !== null) {
                    $attachedSessionIds[$sessionId] = true;
                }
            }

            $attachedPayments = Payment::query()
                ->whereNull('user_id')
                ->whereRaw('LOWER(payer_email) = ?', [$email])
                ->update(['user_id' => $user->id]);

            return [
                'enrollments' => $attachedEnrollments,
                'payments' => $attachedPayments,
            ];
        });
    }
}
