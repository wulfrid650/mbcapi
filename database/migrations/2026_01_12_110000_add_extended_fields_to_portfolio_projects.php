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
            // Rendre created_by nullable
            $table->foreignId('created_by')->nullable()->change();
            
            // Ajouter les nouveaux champs
            if (!Schema::hasColumn('portfolio_projects', 'images')) {
                $table->json('images')->nullable()->after('cover_image');
            }
            if (!Schema::hasColumn('portfolio_projects', 'year')) {
                $table->year('year')->nullable()->after('location');
            }
            if (!Schema::hasColumn('portfolio_projects', 'duration')) {
                $table->string('duration')->nullable()->after('year');
            }
            if (!Schema::hasColumn('portfolio_projects', 'budget')) {
                $table->string('budget')->nullable()->after('duration');
            }
            if (!Schema::hasColumn('portfolio_projects', 'status')) {
                $table->enum('status', ['planned', 'in_progress', 'completed', 'on_hold'])->default('completed')->after('budget');
            }
            if (!Schema::hasColumn('portfolio_projects', 'services')) {
                $table->json('services')->nullable()->after('status');
            }
            if (!Schema::hasColumn('portfolio_projects', 'challenges')) {
                $table->text('challenges')->nullable()->after('services');
            }
            if (!Schema::hasColumn('portfolio_projects', 'results')) {
                $table->text('results')->nullable()->after('challenges');
            }
        });

        // Renommer client_name en client si nécessaire
        if (Schema::hasColumn('portfolio_projects', 'client_name') && !Schema::hasColumn('portfolio_projects', 'client')) {
            Schema::table('portfolio_projects', function (Blueprint $table) {
                $table->renameColumn('client_name', 'client');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('portfolio_projects', function (Blueprint $table) {
            $table->dropColumn(['images', 'year', 'duration', 'budget', 'status', 'services', 'challenges', 'results']);
        });

        if (Schema::hasColumn('portfolio_projects', 'client')) {
            Schema::table('portfolio_projects', function (Blueprint $table) {
                $table->renameColumn('client', 'client_name');
            });
        }
    }
};
