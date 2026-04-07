<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::table('formation_enrollments')->whereNull('session_id')->exists()) {
            throw new \RuntimeException('Impossible de rendre session_id obligatoire : certaines inscriptions existantes n\'ont aucune session associée.');
        }

        Schema::table('formation_enrollments', function (Blueprint $table) {
            $table->unsignedBigInteger('session_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('formation_enrollments', function (Blueprint $table) {
            $table->unsignedBigInteger('session_id')->nullable()->change();
        });
    }
};
