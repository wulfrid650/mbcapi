<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table pour stocker tous les paramètres du site (contact, réseaux sociaux, etc.)
 * Ces paramètres sont modifiables par l'admin via l'interface
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // Clé unique (ex: 'company_phone', 'facebook_url')
            $table->text('value')->nullable(); // Valeur du paramètre
            $table->string('type')->default('text'); // Type: text, textarea, image, boolean, json
            $table->string('group')->default('general'); // Groupe: general, contact, social, seo, etc.
            $table->string('label'); // Label affiché dans l'admin
            $table->text('description')->nullable(); // Description pour l'admin
            $table->boolean('is_public')->default(true); // Visible publiquement via API ?
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
