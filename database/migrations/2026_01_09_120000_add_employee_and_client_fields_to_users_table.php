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
        Schema::table('users', function (Blueprint $table) {
            // Champs employés (admin, secretaire, chef_chantier)
            if (!Schema::hasColumn('users', 'employee_id')) {
                $table->string('employee_id')->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('users', 'address')) {
                $table->text('address')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('users', 'emergency_contact')) {
                $table->string('emergency_contact')->nullable()->after('address');
            }
            if (!Schema::hasColumn('users', 'emergency_phone')) {
                $table->string('emergency_phone')->nullable()->after('emergency_contact');
            }
            
            // Champs pour l'invitation employé
            if (!Schema::hasColumn('users', 'invitation_token')) {
                $table->string('invitation_token')->nullable()->unique()->after('is_active');
            }
            if (!Schema::hasColumn('users', 'invitation_expires_at')) {
                $table->timestamp('invitation_expires_at')->nullable()->after('invitation_token');
            }
            if (!Schema::hasColumn('users', 'profile_completed')) {
                $table->boolean('profile_completed')->default(false)->after('invitation_expires_at');
            }
            
            // Champs clients
            if (!Schema::hasColumn('users', 'company_name')) {
                $table->string('company_name')->nullable()->after('profile_completed');
            }
            if (!Schema::hasColumn('users', 'company_address')) {
                $table->text('company_address')->nullable()->after('company_name');
            }
            if (!Schema::hasColumn('users', 'project_type')) {
                $table->string('project_type')->nullable()->after('company_address');
            }
            if (!Schema::hasColumn('users', 'project_description')) {
                $table->text('project_description')->nullable()->after('project_type');
            }
            
            // Champs apprenants
            if (!Schema::hasColumn('users', 'formation_id')) {
                $table->unsignedBigInteger('formation_id')->nullable()->after('formation');
            }
            if (!Schema::hasColumn('users', 'enrollment_date')) {
                $table->date('enrollment_date')->nullable()->after('formation_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [
                'employee_id',
                'address',
                'emergency_contact',
                'emergency_phone',
                'invitation_token',
                'invitation_expires_at',
                'profile_completed',
                'company_name',
                'company_address',
                'project_type',
                'project_description',
                'formation_id',
                'enrollment_date',
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
