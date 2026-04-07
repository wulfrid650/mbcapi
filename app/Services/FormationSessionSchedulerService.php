<?php

namespace App\Services;

use App\Models\Formation;
use App\Models\FormationSession;
use App\Models\SiteSetting;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FormationSessionSchedulerService
{
    private const SETTING_AUTO_ENABLED = 'formation_sessions_auto_enabled';
    private const SETTING_START_DATE = 'formation_sessions_start_date';
    private const SETTING_INTERVAL_MONTHS = 'formation_sessions_interval_months';
    private const SETTING_MONTHS_AHEAD = 'formation_sessions_months_ahead';

    /**
     * Génère les sessions futures pour toutes les formations actives (ou une formation précise).
     */
    public function ensureUpcomingSessions(?Formation $formation = null): int
    {
        if (!$this->isAutomationEnabled()) {
            return 0;
        }

        $startDate = $this->getStartDate();
        if (!$startDate) {
            return 0;
        }

        $intervalMonths = $this->getIntervalMonths();
        $monthsAhead = $this->getMonthsAhead();
        $targetDate = Carbon::now()->addMonths($monthsAhead)->startOfDay();

        $formations = $formation
            ? Collection::make([$formation])
            : Formation::query()->active()->get();

        $createdCount = 0;

        foreach ($formations as $item) {
            $createdCount += $this->generateSessionsForFormation($item, $startDate, $intervalMonths, $targetDate);
        }

        return $createdCount;
    }

    private function generateSessionsForFormation(Formation $formation, Carbon $startDate, int $intervalMonths, Carbon $targetDate): int
    {
        $created = 0;

        $durationDays = $this->resolveDurationDays($formation);
        $maxStudents = $formation->max_students ?? 15;
        $formateurId = $formation->formateur_id;

        $scheduleDate = $startDate->copy();
        $today = Carbon::now()->startOfDay();

        while ($scheduleDate->lte($targetDate)) {
            if ($scheduleDate->lt($today)) {
                $scheduleDate = $scheduleDate->copy()->addMonthsNoOverflow($intervalMonths);
                continue;
            }

            $exists = $formation->sessions()
                ->whereDate('start_date', $scheduleDate->toDateString())
                ->exists();

            if (!$exists) {
                $endDate = $scheduleDate->copy()->addDays($durationDays - 1);

                FormationSession::create([
                    'formation_id' => $formation->id,
                    'formateur_id' => $formateurId,
                    'start_date' => $scheduleDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'start_time' => '08:00',
                    'end_time' => '17:00',
                    'location' => null,
                    'max_students' => $maxStudents,
                    'status' => 'planned',
                ]);

                $created++;
            }

            $scheduleDate = $scheduleDate->copy()->addMonthsNoOverflow($intervalMonths);
        }

        return $created;
    }

    private function resolveDurationDays(Formation $formation): int
    {
        if (!empty($formation->duration_days)) {
            return max(1, (int) $formation->duration_days);
        }

        if (!empty($formation->duration_hours)) {
            return max(1, (int) ceil($formation->duration_hours / 8));
        }

        return 1;
    }

    private function isAutomationEnabled(): bool
    {
        return (bool) SiteSetting::get(self::SETTING_AUTO_ENABLED, false);
    }

    private function getStartDate(): ?Carbon
    {
        $value = SiteSetting::get(self::SETTING_START_DATE);
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getIntervalMonths(): int
    {
        $value = (int) SiteSetting::get(self::SETTING_INTERVAL_MONTHS, 2);
        return $value > 0 ? $value : 2;
    }

    private function getMonthsAhead(): int
    {
        $value = (int) SiteSetting::get(self::SETTING_MONTHS_AHEAD, 6);
        return $value > 0 ? $value : 6;
    }
}
