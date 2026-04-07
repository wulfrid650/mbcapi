<?php

namespace App\Services;

use App\Models\PortfolioProject;
use App\Models\User;
use Illuminate\Support\Arr;
use RuntimeException;

class ProjectPhaseWorkflowService
{
    private const PHASES = [
        'etudes_permis' => ['label' => 'Études et permis', 'progress_floor' => 0],
        'fondations' => ['label' => 'Fondations', 'progress_floor' => 20],
        'gros_oeuvre' => ['label' => 'Gros œuvre', 'progress_floor' => 35],
        'second_oeuvre' => ['label' => 'Second œuvre', 'progress_floor' => 60],
        'finitions' => ['label' => 'Finitions', 'progress_floor' => 80],
        'reception' => ['label' => 'Réception', 'progress_floor' => 95],
    ];

    public static function phaseKeys(): array
    {
        return array_keys(self::PHASES);
    }

    public function getPhaseState(PortfolioProject $project): array
    {
        $workflow = $this->extractWorkflow($project);
        $currentPhase = (string) ($workflow['current_phase'] ?? $this->derivePhaseFromProgress((int) ($project->progress ?? 0)));
        $pendingRequest = is_array($workflow['pending_request'] ?? null) ? $workflow['pending_request'] : null;
        $history = array_values(array_filter($workflow['history'] ?? [], 'is_array'));

        return [
            'current_phase' => $currentPhase,
            'current_phase_label' => self::PHASES[$currentPhase]['label'] ?? $currentPhase,
            'available_phases' => $this->phaseOptions(),
            'pending_request' => $pendingRequest,
            'history' => $history,
        ];
    }

    public function requestTransition(PortfolioProject $project, User $actor, string $toPhase, ?string $note = null): array
    {
        $this->assertValidPhase($toPhase);

        if ($this->isStaffUser($actor)) {
            $phaseState = $this->applyTransition($project, $actor, $toPhase, $note);
            return [
                'mode' => 'applied',
                'phase_state' => $phaseState,
            ];
        }

        $workflow = $this->extractWorkflow($project);
        $fromPhase = (string) ($workflow['current_phase'] ?? $this->derivePhaseFromProgress((int) ($project->progress ?? 0)));

        $workflow['pending_request'] = [
            'from_phase' => $fromPhase,
            'from_phase_label' => self::PHASES[$fromPhase]['label'] ?? $fromPhase,
            'to_phase' => $toPhase,
            'to_phase_label' => self::PHASES[$toPhase]['label'] ?? $toPhase,
            'note' => $note,
            'requested_by' => $actor->id,
            'requested_by_role' => $this->resolveRoleSlug($actor),
            'requested_at' => now()->toIso8601String(),
        ];

        $metadata = $this->extractMetadata($project);
        $metadata['phase_workflow'] = $workflow;
        $project->update(['metadata' => $metadata]);

        return [
            'mode' => 'pending_approval',
            'phase_state' => $this->getPhaseState($project->fresh()),
        ];
    }

    public function approvePending(PortfolioProject $project, User $approver, ?string $note = null): array
    {
        $workflow = $this->extractWorkflow($project);
        $pending = $workflow['pending_request'] ?? null;

        if (!is_array($pending) || empty($pending['to_phase'])) {
            throw new RuntimeException('Aucune demande de changement de phase en attente.');
        }

        $toPhase = (string) $pending['to_phase'];
        $this->assertValidPhase($toPhase);

        $phaseState = $this->applyTransition($project, $approver, $toPhase, $note, [
            'requested_by' => $pending['requested_by'] ?? null,
            'requested_by_role' => $pending['requested_by_role'] ?? null,
            'requested_at' => $pending['requested_at'] ?? null,
            'request_note' => $pending['note'] ?? null,
        ]);

        return [
            'mode' => 'approved',
            'phase_state' => $phaseState,
        ];
    }

    public function rejectPending(PortfolioProject $project, User $approver, ?string $note = null): array
    {
        $workflow = $this->extractWorkflow($project);
        $pending = $workflow['pending_request'] ?? null;

        if (!is_array($pending) || empty($pending['to_phase'])) {
            throw new RuntimeException('Aucune demande de changement de phase en attente.');
        }

        $history = is_array($workflow['history'] ?? null) ? $workflow['history'] : [];
        $history[] = [
            'status' => 'rejected',
            'from_phase' => $pending['from_phase'] ?? null,
            'from_phase_label' => $pending['from_phase_label'] ?? null,
            'to_phase' => $pending['to_phase'] ?? null,
            'to_phase_label' => $pending['to_phase_label'] ?? null,
            'requested_by' => $pending['requested_by'] ?? null,
            'requested_by_role' => $pending['requested_by_role'] ?? null,
            'requested_at' => $pending['requested_at'] ?? null,
            'request_note' => $pending['note'] ?? null,
            'reviewed_by' => $approver->id,
            'reviewed_by_role' => $this->resolveRoleSlug($approver),
            'reviewed_at' => now()->toIso8601String(),
            'review_note' => $note,
        ];

        $workflow['history'] = array_slice($history, -100);
        $workflow['pending_request'] = null;
        $workflow['updated_at'] = now()->toIso8601String();
        $workflow['updated_by'] = $approver->id;
        $workflow['updated_by_role'] = $this->resolveRoleSlug($approver);

        $metadata = $this->extractMetadata($project);
        $metadata['phase_workflow'] = $workflow;
        $project->update(['metadata' => $metadata]);

        return [
            'mode' => 'rejected',
            'phase_state' => $this->getPhaseState($project->fresh()),
        ];
    }

    private function applyTransition(
        PortfolioProject $project,
        User $actor,
        string $toPhase,
        ?string $note = null,
        array $context = []
    ): array {
        $this->assertValidPhase($toPhase);

        $workflow = $this->extractWorkflow($project);
        $fromPhase = (string) ($workflow['current_phase'] ?? $this->derivePhaseFromProgress((int) ($project->progress ?? 0)));
        $history = is_array($workflow['history'] ?? null) ? $workflow['history'] : [];

        $history[] = [
            'status' => 'approved',
            'from_phase' => $fromPhase,
            'from_phase_label' => self::PHASES[$fromPhase]['label'] ?? $fromPhase,
            'to_phase' => $toPhase,
            'to_phase_label' => self::PHASES[$toPhase]['label'] ?? $toPhase,
            'requested_by' => $context['requested_by'] ?? $actor->id,
            'requested_by_role' => $context['requested_by_role'] ?? $this->resolveRoleSlug($actor),
            'requested_at' => $context['requested_at'] ?? now()->toIso8601String(),
            'request_note' => $context['request_note'] ?? null,
            'approved_by' => $actor->id,
            'approved_by_role' => $this->resolveRoleSlug($actor),
            'approved_at' => now()->toIso8601String(),
            'approval_note' => $note,
        ];

        $workflow['current_phase'] = $toPhase;
        $workflow['history'] = array_slice($history, -100);
        $workflow['pending_request'] = null;
        $workflow['updated_at'] = now()->toIso8601String();
        $workflow['updated_by'] = $actor->id;
        $workflow['updated_by_role'] = $this->resolveRoleSlug($actor);

        $metadata = $this->extractMetadata($project);
        $metadata['phase_workflow'] = $workflow;

        $updateData = ['metadata' => $metadata];
        $phaseFloor = (int) Arr::get(self::PHASES, "{$toPhase}.progress_floor", 0);
        if ((int) ($project->progress ?? 0) < $phaseFloor) {
            $updateData['progress'] = $phaseFloor;
        }

        if (in_array((string) $project->status, ['planned', 'pending'], true)) {
            $updateData['status'] = 'in_progress';
        }

        $project->update($updateData);
        return $this->getPhaseState($project->fresh());
    }

    private function extractMetadata(PortfolioProject $project): array
    {
        return is_array($project->metadata) ? $project->metadata : [];
    }

    private function extractWorkflow(PortfolioProject $project): array
    {
        $metadata = $this->extractMetadata($project);
        $workflow = $metadata['phase_workflow'] ?? [];
        return is_array($workflow) ? $workflow : [];
    }

    private function derivePhaseFromProgress(int $progress): string
    {
        $current = 'etudes_permis';
        foreach (self::PHASES as $key => $config) {
            if ($progress >= (int) $config['progress_floor']) {
                $current = $key;
            }
        }

        return $current;
    }

    private function phaseOptions(): array
    {
        $options = [];
        foreach (self::PHASES as $key => $config) {
            $options[] = [
                'key' => $key,
                'label' => $config['label'],
                'progress_floor' => $config['progress_floor'],
            ];
        }

        return $options;
    }

    private function assertValidPhase(string $phase): void
    {
        if (!array_key_exists($phase, self::PHASES)) {
            throw new RuntimeException('Phase chantier invalide.');
        }
    }

    private function isStaffUser(User $user): bool
    {
        if ($user->isAdmin() || $user->hasRole('admin')) {
            return true;
        }

        $role = $this->resolveRoleSlug($user);
        if ($role === 'admin' || $role === 'secretaire') {
            return true;
        }

        return $user->hasRole('secretaire');
    }

    private function resolveRoleSlug(User $user): string
    {
        return (string) ($user->getActiveRoleSlug() ?: $user->role ?: '');
    }
}
