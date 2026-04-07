<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Amélioration des paiements : motifs, reçus, et tracking des codes promos
 */
return new class extends Migration
{
    public function up(): void
    {
        // Ajouter des champs au paiement pour les motifs et reçus
        Schema::table('payments', function (Blueprint $table) {
            // Motif/raison du paiement
            $table->string('purpose')->nullable()->after('description'); // formation_payment, service_payment, project_payment, etc.
            $table->string('purpose_detail')->nullable()->after('purpose'); // Détail libre du motif
            
            // Informations de reçu
            $table->string('receipt_number')->nullable()->unique()->after('reference');
            $table->timestamp('receipt_generated_at')->nullable();
            $table->string('receipt_path')->nullable(); // Chemin du PDF généré
            
            // Code promo appliqué
            $table->foreignId('promo_code_id')->nullable()->after('metadata')->constrained('promo_codes')->nullOnDelete();
            $table->decimal('original_amount', 12, 2)->nullable()->after('amount');
            $table->decimal('discount_amount', 12, 2)->nullable()->after('original_amount');
            
            // Informations payeur (pour les paiements invités)
            $table->string('payer_name')->nullable();
            $table->string('payer_email')->nullable();
            $table->string('payer_phone')->nullable();
        });

        // Créer une table pour tracker l'utilisation des codes promo par utilisateur
        Schema::create('promo_code_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_code_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('guest_email')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained()->onDelete('cascade');
            $table->decimal('discount_applied', 12, 2);
            $table->timestamps();

            // Index pour vérifier l'utilisation unique par utilisateur
            $table->index(['promo_code_id', 'user_id']);
            $table->index(['promo_code_id', 'guest_email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_code_usages');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['promo_code_id']);
            $table->dropColumn([
                'purpose',
                'purpose_detail',
                'receipt_number',
                'receipt_generated_at',
                'receipt_path',
                'promo_code_id',
                'original_amount',
                'discount_amount',
                'payer_name',
                'payer_email',
                'payer_phone',
            ]);
        });
    }
};
