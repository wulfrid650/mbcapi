<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('portfolio_projects', function (Blueprint $table) {
            if (!Schema::hasColumn('portfolio_projects', 'client_id')) {
                $table->foreignId('client_id')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('portfolio_projects', 'client_email')) {
                $table->string('client_email')->nullable()->after('client_id');
            }
            if (!Schema::hasColumn('portfolio_projects', 'start_date')) {
                $table->date('start_date')->nullable()->after('client_email');
            }
            if (!Schema::hasColumn('portfolio_projects', 'expected_end_date')) {
                $table->date('expected_end_date')->nullable()->after('start_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('portfolio_projects', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropColumn(['client_id', 'client_email', 'start_date', 'expected_end_date']);
        });
    }
};
