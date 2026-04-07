<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formation_enrollment_id')->constrained()->cascadeOnDelete()->unique();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('pending');
            $table->timestamp('requested_at')->nullable();
            $table->foreignId('decision_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decision_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->foreignId('invalidated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('invalidated_at')->nullable();
            $table->text('invalidation_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'requested_at']);
        });

        $existingCertificates = DB::table('certificates')->get();

        foreach ($existingCertificates as $certificate) {
            $status = $certificate->revoked_at ? 'invalidated' : 'approved';
            $decisionAt = $certificate->issued_at ?? $certificate->created_at;

            DB::table('certificate_requests')->updateOrInsert(
                ['formation_enrollment_id' => $certificate->formation_enrollment_id],
                [
                    'requested_by' => null,
                    'status' => $status,
                    'requested_at' => $decisionAt,
                    'decision_by' => $certificate->generated_by,
                    'decision_at' => $decisionAt,
                    'decision_notes' => $status === 'approved'
                        ? 'Demande historique migrée automatiquement.'
                        : 'Certificat historique migré automatiquement.',
                    'invalidated_by' => $status === 'invalidated' ? $certificate->generated_by : null,
                    'invalidated_at' => $certificate->revoked_at,
                    'invalidation_reason' => $status === 'invalidated' ? ($certificate->revoked_reason ?: 'Certificat historique invalidé.') : null,
                    'metadata' => json_encode([
                        'migrated_from_certificate' => true,
                        'certificate_reference' => $certificate->reference,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_requests');
    }
};
