<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table des inscriptions aux formations (sessions)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formation_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formation_id')->constrained()->onDelete('cascade');
            $table->foreignId('formateur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time')->default('08:00');
            $table->time('end_time')->default('17:00');
            $table->string('location')->nullable(); // Lieu de la formation
            $table->integer('max_students')->default(15);
            $table->string('status')->default('planned'); // planned, ongoing, completed, cancelled
            $table->timestamps();
        });

        // Table pivot pour les inscriptions des apprenants
        Schema::create('formation_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Apprenant
            $table->foreignId('formation_session_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('pending'); // pending, confirmed, completed, cancelled
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->boolean('payment_complete')->default(false);
            $table->integer('progression')->default(0); // 0-100%
            $table->timestamp('enrolled_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->unique(['user_id', 'formation_session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formation_enrollments');
        Schema::dropIfExists('formation_sessions');
    }
};
