<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table des témoignages/avis clients
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('author_name');
            $table->string('author_role')->nullable(); // Ex: "Directeur, Société XYZ"
            $table->string('author_company')->nullable();
            $table->string('author_image')->nullable();
            $table->text('content'); // Le témoignage
            $table->integer('rating')->default(5); // Note sur 5
            $table->string('project_type')->nullable(); // Type de projet réalisé
            $table->foreignId('portfolio_project_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_approved')->default(false); // Validé par l'admin
            $table->boolean('is_featured')->default(false);
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
