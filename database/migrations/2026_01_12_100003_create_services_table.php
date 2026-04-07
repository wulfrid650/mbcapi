<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table des services proposés par MBC (construction, rénovation, etc.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('short_description'); // Description courte pour les listes
            $table->text('description'); // Description complète
            $table->text('features')->nullable(); // Caractéristiques (JSON array)
            $table->string('icon')->nullable(); // Nom de l'icône (lucide-react)
            $table->string('cover_image')->nullable();
            $table->decimal('starting_price', 12, 2)->nullable(); // Prix à partir de
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
