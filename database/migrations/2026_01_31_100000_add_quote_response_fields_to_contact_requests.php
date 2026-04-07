<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ajouter des colonnes à contact_requests pour gérer les réponses
        Schema::table('contact_requests', function (Blueprint $table) {
            $table->string('response_document')->nullable()->after('responded_at');
            $table->text('response_message')->nullable()->after('response_document');
            $table->timestamp('response_sent_at')->nullable()->after('response_message');
            $table->string('quote_number')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('contact_requests', function (Blueprint $table) {
            $table->dropColumn(['response_document', 'response_message', 'response_sent_at', 'quote_number']);
        });
    }
};
