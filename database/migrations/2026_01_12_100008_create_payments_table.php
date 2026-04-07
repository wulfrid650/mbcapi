<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table des paiements
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique(); // PAY-2026-XXXXXX
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('payable_type'); // Formation, Project, etc.
            $table->unsignedBigInteger('payable_id');
            $table->decimal('amount', 12, 2);
            $table->string('currency')->default('XAF'); // FCFA
            $table->string('method'); // orange_money, mtn_momo, carte_bancaire, especes, virement
            $table->string('status')->default('pending'); // pending, completed, failed, refunded
            $table->string('transaction_id')->nullable(); // ID transaction externe
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Données supplémentaires
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index(['payable_type', 'payable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
