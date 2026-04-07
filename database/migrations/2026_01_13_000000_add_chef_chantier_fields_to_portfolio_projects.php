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
        Schema::table('portfolio_projects', function (Blueprint $table) {
            if (!Schema::hasColumn('portfolio_projects', 'chef_chantier_id')) {
                $table->foreignId('chef_chantier_id')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('portfolio_projects', 'team_ids')) {
                $table->json('team_ids')->nullable()->after('chef_chantier_id');
            }
            if (!Schema::hasColumn('portfolio_projects', 'status')) {
                $table->string('status')->default('pending')->after('team_ids');
            }
            if (!Schema::hasColumn('portfolio_projects', 'progress')) {
                $table->integer('progress')->default(0)->after('status');
            }
            if (!Schema::hasColumn('portfolio_projects', 'metadata')) {
                $table->json('metadata')->nullable()->after('progress');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('portfolio_projects', function (Blueprint $table) {
            $table->dropForeign(['chef_chantier_id']);
            $table->dropColumn(['chef_chantier_id', 'team_ids', 'status', 'progress', 'metadata']);
        });
    }
};
