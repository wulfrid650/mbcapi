<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolio_projects', function (Blueprint $table) {
            if (!Schema::hasColumn('portfolio_projects', 'linked_quote_request_id')) {
                $table->foreignId('linked_quote_request_id')
                    ->nullable()
                    ->after('client_id')
                    ->constrained('contact_requests')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('portfolio_projects', function (Blueprint $table) {
            if (Schema::hasColumn('portfolio_projects', 'linked_quote_request_id')) {
                $table->dropForeign(['linked_quote_request_id']);
                $table->dropColumn('linked_quote_request_id');
            }
        });
    }
};
