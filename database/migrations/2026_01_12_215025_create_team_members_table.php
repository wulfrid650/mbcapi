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
        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('position'); // Directeur Général, Directeur Technique, etc.
            $table->string('department')->nullable(); // Direction, Technique, Projets, etc.
            $table->string('photo')->nullable(); // Path to photo
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('bio')->nullable();
            $table->json('social_links')->nullable(); // LinkedIn, Twitter, etc.
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('show_on_website')->default(true);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_members');
    }
};
