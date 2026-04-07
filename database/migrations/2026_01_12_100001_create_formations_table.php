<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table des formations proposées par MBC
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formations', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description');
            $table->text('objectives')->nullable(); // Objectifs pédagogiques (JSON array)
            $table->text('prerequisites')->nullable(); // Prérequis (JSON array)
            $table->text('program')->nullable(); // Programme détaillé (JSON array)
            $table->integer('duration_hours'); // Durée en heures
            $table->integer('duration_days')->nullable(); // Durée en jours
            $table->decimal('price', 12, 2);
            $table->string('level')->default('debutant'); // debutant, intermediaire, avance
            $table->string('category'); // maconnerie, electricite, plomberie, etc.
            $table->string('cover_image')->nullable();
            $table->integer('max_students')->default(15);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false); // Mise en avant sur le site
            $table->integer('display_order')->default(0);
            $table->foreignId('formateur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formations');
    }
};
