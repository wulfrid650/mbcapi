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
        Schema::table('formation_enrollments', function (Blueprint $table) {
            // Rendre user_id nullable pour les inscriptions sans compte
            $table->unsignedBigInteger('user_id')->nullable()->change();
            
            // Ajouter formation_id directement
            $table->foreignId('formation_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            
            // Renommer formation_session_id en session_id pour cohérence
            if (Schema::hasColumn('formation_enrollments', 'formation_session_id')) {
                $table->renameColumn('formation_session_id', 'session_id');
            }
            
            // Champs pour inscriptions invités (sans compte)
            $table->string('first_name')->nullable()->after('formation_id');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('email')->nullable()->after('last_name');
            $table->string('phone')->nullable()->after('email');
            $table->text('message')->nullable()->after('phone');
            
            // Métadonnées JSON
            $table->json('metadata')->nullable()->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('formation_enrollments', function (Blueprint $table) {
            $table->dropForeign(['formation_id']);
            $table->dropColumn(['formation_id', 'first_name', 'last_name', 'email', 'phone', 'message', 'metadata']);
            if (Schema::hasColumn('formation_enrollments', 'session_id')) {
                $table->renameColumn('session_id', 'formation_session_id');
            }
        });
    }
};
