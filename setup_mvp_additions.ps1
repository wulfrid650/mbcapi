<#
.SYNOPSIS
    Ajout des tables MVP "Réaliste" pour MBC (Accès Tokens, Ouvriers, Paiements).
#>

Write-Host "🚀 Ajout des fonctionnalités BTP Pro..." -ForegroundColor Cyan

# Vérification artisan
if (-not (Test-Path "artisan")) {
    Write-Error "❌ Fichier 'artisan' introuvable. Exécutez ce script dans le dossier mbcapi."
    exit
}

function Write-MigrationContent {
    param ([string]$Pattern, [string]$Content)
    $file = Get-ChildItem "database/migrations/$Pattern" | Sort-Object CreationTime | Select-Object -Last 1
    if ($file) {
        Set-Content -Path $file.FullName -Value $Content
        Write-Host "   ✅ Configuré : $($file.Name)" -ForegroundColor Green
    }
}

# 1. ACCÈS CLIENT SANS COMPTE (Magic Links)
Write-Host "`n🔑 1. Création table Access Tokens..." -ForegroundColor Yellow
php artisan make:migration create_project_access_tokens_table

$tokensContent = @'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('project_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            
            $table->string('token', 64)->unique(); // Le token secret URL
            $table->string('contact_email')->nullable(); // A qui a-t-on envoyé le lien ?
            $table->timestamp('expires_at');
            $table->timestamp('last_accessed_at')->nullable();
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('project_access_tokens');
    }
};
'@
Write-MigrationContent -Pattern "*create_project_access_tokens_table.php" -Content $tokensContent

# 2. AFFECTATION OUVRIERS (Pivot)
Write-Host "`n👷 2. Création table Workers Pivot..." -ForegroundColor Yellow
php artisan make:migration create_project_user_pivot_table

$workersContent = @'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('project_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // L'ouvrier
            
            $table->string('role_on_site')->nullable(); // ex: "Chef d'équipe", "Électricien"
            $table->date('assigned_at')->useCurrent();
            
            $table->unique(['project_id', 'user_id']); // Pas de doublon
        });
    }

    public function down()
    {
        Schema::dropIfExists('project_user');
    }
};
'@
Write-MigrationContent -Pattern "*create_project_user_pivot_table.php" -Content $workersContent

# 3. PAIEMENTS & SITUATIONS
Write-Host "`n💰 3. Création table Paiements..." -ForegroundColor Yellow
php artisan make:migration create_project_payments_table

$paymentContent = @'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('project_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            
            $table->string('title'); // ex: "Acompte Démarrage", "Situation N°1"
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['PENDING', 'PAID', 'OVERDUE', 'CANCELLED'])->default('PENDING');
            
            $table->date('due_date'); // Date échéance
            $table->date('paid_at')->nullable();
            $table->string('payment_method')->nullable(); // Virement, Chèque, Espèces
            $table->string('invoice_reference')->nullable(); // N° Facture
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('project_payments');
    }
};
'@
Write-MigrationContent -Pattern "*create_project_payments_table.php" -Content $paymentContent

Write-Host "`n🎉 TABLES MVP AJOUTÉES !" -ForegroundColor Cyan
Write-Host "👉 Lancez : php artisan migrate" -ForegroundColor Cyan
