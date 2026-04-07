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
        // Create roles table
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // admin, secretaire, formateur, etc.
            $table->string('name'); // Display name
            $table->string('description')->nullable();
            $table->boolean('is_staff')->default(false); // Internal staff role
            $table->boolean('can_self_register')->default(false); // Can users self-register with this role
            $table->timestamps();
        });

        // Create pivot table for user roles
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->boolean('is_primary')->default(false); // Primary/default role for user
            $table->json('metadata')->nullable(); // Role-specific data (e.g., formation_id for apprenant)
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamps();

            $table->unique(['user_id', 'role_id']);
        });

        // Insert default roles
        DB::table('roles')->insert([
            [
                'slug' => 'admin',
                'name' => 'Administrateur',
                'description' => 'Accès complet à toutes les fonctionnalités',
                'is_staff' => true,
                'can_self_register' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'secretaire',
                'name' => 'Secrétaire',
                'description' => 'Gestion administrative et suivi des dossiers',
                'is_staff' => true,
                'can_self_register' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'formateur',
                'name' => 'Formateur',
                'description' => 'Gestion des formations et des apprenants',
                'is_staff' => true,
                'can_self_register' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'chef_chantier',
                'name' => 'Chef de chantier',
                'description' => 'Supervision des chantiers et équipes',
                'is_staff' => true,
                'can_self_register' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'apprenant',
                'name' => 'Apprenant',
                'description' => 'Accès aux formations et contenus pédagogiques',
                'is_staff' => false,
                'can_self_register' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'client',
                'name' => 'Client',
                'description' => 'Suivi de projet et communication',
                'is_staff' => false,
                'can_self_register' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Migrate existing users to new roles system
        $users = DB::table('users')->whereNotNull('role')->get();
        
        foreach ($users as $user) {
            $role = DB::table('roles')->where('slug', $user->role)->first();
            
            if ($role) {
                DB::table('user_roles')->insert([
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    'is_primary' => true,
                    'assigned_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Add active_role_id to users table for quick access to current role
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('active_role_id')->nullable()->after('role')->constrained('roles')->nullOnDelete();
        });

        // Update active_role_id based on existing role
        $users = DB::table('users')->whereNotNull('role')->get();
        foreach ($users as $user) {
            $role = DB::table('roles')->where('slug', $user->role)->first();
            if ($role) {
                DB::table('users')->where('id', $user->id)->update(['active_role_id' => $role->id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['active_role_id']);
            $table->dropColumn('active_role_id');
        });

        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('roles');
    }
};
