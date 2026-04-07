<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modifier l'ENUM pour ajouter le rôle formateur (MySQL/MariaDB seulement)
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'secretaire', 'apprenant', 'client', 'chef_chantier', 'formateur') DEFAULT 'apprenant'");
        }
        
        // Ajouter les champs spécifiques au formateur
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'speciality')) {
                $table->string('speciality')->nullable()->after('formation');
            }
            if (!Schema::hasColumn('users', 'bio')) {
                $table->text('bio')->nullable()->after('speciality');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'secretaire', 'apprenant', 'client', 'chef_chantier') DEFAULT 'apprenant'");
        }
        
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'speciality')) {
                $table->dropColumn('speciality');
            }
            if (Schema::hasColumn('users', 'bio')) {
                $table->dropColumn('bio');
            }
        });
    }
};
