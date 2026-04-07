<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Équipes de construction (Groupes)
        Schema::create('construction_teams', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // ex: Équipe Maçonnerie A
            $table->string('leader_name'); // ex: Jean Nkomo (ou foreign key vers users si besoin)
            $table->integer('members_count')->default(0);
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('specialization'); // Maçonnerie, Électricité...
            $table->integer('projects_count')->default(0); // Cache count
            $table->string('status')->default('Actif'); // Actif, En pause, Inactif
            $table->timestamps();
        });

        // 2. Avancements (Project Updates)
        Schema::create('project_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_project_id')->constrained('portfolio_projects')->onDelete('cascade');
            $table->string('title');
            $table->text('description');
            $table->date('date');
            $table->string('author_name'); // Pour l'affichage direct
            $table->integer('images_count')->default(0);
            $table->string('status')->default('Publié'); // Publié, Brouillon, Archivé
            $table->timestamps();
        });

        // 3. Rapports (Reports)
        Schema::create('project_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_project_id')->constrained('portfolio_projects')->onDelete('cascade');
            $table->string('title');
            $table->string('period'); // ex: Janvier 2026
            $table->string('type'); // Avancement, Sécurité, Effectif, Matériaux
            $table->string('author_name');
            $table->date('date');
            $table->integer('pages_count')->default(1);
            $table->string('status')->default('Complété');
            $table->string('file_path')->nullable(); // PDF path
            $table->timestamps();
        });

        // 4. Messages
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            // Allow storing sender/recipient names for system messages or mock flexibility
            $table->string('sender_name')->nullable();
            
            $table->foreignId('portfolio_project_id')->nullable()->constrained('portfolio_projects')->nullOnDelete();
            $table->string('subject');
            $table->text('content');
            $table->timestamp('read_at')->nullable();
            $table->string('type')->default('message'); // message, notification
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('project_reports');
        Schema::dropIfExists('project_updates');
        Schema::dropIfExists('construction_teams');
    }
};
