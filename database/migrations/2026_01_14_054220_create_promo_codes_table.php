<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table des codes promo pour les formations
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->enum('type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('value', 10, 2); // 10% ou 5000 FCFA
            $table->integer('max_uses')->nullable(); // null = illimité
            $table->integer('used_count')->default(0);
            $table->dateTime('valid_from')->nullable();
            $table->dateTime('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->json('formations')->nullable(); // IDs des formations concernées, null = toutes
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['code', 'is_active']);
        });

        // Ajouter colonne promo_code_id à formation_enrollments
        Schema::table('formation_enrollments', function (Blueprint $table) {
            $table->foreignId('promo_code_id')->nullable()->constrained('promo_codes')->nullOnDelete();
            $table->decimal('discount_amount', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('formation_enrollments', function (Blueprint $table) {
            $table->dropForeign(['promo_code_id']);
            $table->dropColumn(['promo_code_id', 'discount_amount']);
        });

        Schema::dropIfExists('promo_codes');
    }
};
