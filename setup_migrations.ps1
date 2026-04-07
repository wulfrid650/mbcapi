<#
.SYNOPSIS
    Script d'automatisation des migrations Laravel pour MBC Digitization (Corrigé).
#>

Write-Host "🚀 Démarrage de l'installation des migrations MBC..." -ForegroundColor Cyan

if (-not (Test-Path "artisan")) {
    Write-Error "❌ Fichier 'artisan' introuvable. Êtes-vous bien à la racine de votre projet Laravel ?"
    exit
}

function Write-MigrationContent {
    param (
        [string]$Pattern,
        [string]$Content
    )
    # On cherche le dernier fichier qui correspond au pattern
    $file = Get-ChildItem "database/migrations/$Pattern" | Sort-Object CreationTime | Select-Object -Last 1
    if ($file) {
        # On remplace tout le contenu du fichier
        Set-Content -Path $file.FullName -Value $Content
        Write-Host "   ✅ Configuré : $($file.Name)" -ForegroundColor Green
    } else {
        Write-Error "   ❌ Fichier de migration introuvable pour le pattern : $Pattern"
    }
}

# --- 1. CONFIGURATION UTILISATEURS ---
Write-Host "`n📦 1. Configuration de la table Users..." -ForegroundColor Yellow
php artisan make:migration add_fields_and_otp_to_users_table

# Note: Utilisation de @' (Single Quote) pour éviter l'interprétation des variables PHP par PowerShell
$usersContent = @'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Rôles et Profil
            if (!Schema::hasColumn('users', 'role')) {
                $table->enum('role', ['ADMIN', 'MANAGER', 'CLIENT', 'WORKER'])->default('CLIENT');
            }
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable();
            }
            if (!Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar')->nullable();
            }
            
            // Sécurité OTP
            if (!Schema::hasColumn('users', 'two_factor_code')) {
                $table->string('two_factor_code')->nullable();
            }
            if (!Schema::hasColumn('users', 'two_factor_expires_at')) {
                $table->timestamp('two_factor_expires_at')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'phone', 'avatar', 'two_factor_code', 'two_factor_expires_at']);
        });
    }
};
'@
Write-MigrationContent -Pattern "*add_fields_and_otp_to_users_table.php" -Content $usersContent


# --- 2. PROJETS ---
Write-Host "`n🏗️ 2. Création de la table Projects..." -ForegroundColor Yellow
php artisan make:migration create_projects_table

$projectsContent = @'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('address');
            
            // GPS
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            $table->string('status')->default('PLANNING');
            $table->date('start_date')->nullable();
            $table->date('expected_end_date')->nullable();

            // Relations
            $table->foreignId('client_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('manager_id')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('projects');
    }
};
'@
Write-MigrationContent -Pattern "*create_projects_table.php" -Content $projectsContent


# --- 3. PHASES ---
Write-Host "`n📅 3. Création de la table Project Phases..." -ForegroundColor Yellow
php artisan make:migration create_project_phases_table

$phasesContent = @'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('project_phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->string('name');
            $table->string('status')->default('PENDING');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('project_phases');
    }
};
'@
Write-MigrationContent -Pattern "*create_project_phases_table.php" -Content $phasesContent


# --- 4. DAILY LOGS ---
Write-Host "`n📝 4. Création de la table Daily Logs..." -ForegroundColor Yellow
php artisan make:migration create_daily_logs_table

$logsContent = @'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('daily_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('author_id')->constrained('users');
            $table->date('date');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('daily_logs');
    }
};
'@
Write-MigrationContent -Pattern "*create_daily_logs_table.php" -Content $logsContent


# --- 5. SÉCURITÉ & INCIDENTS ---
Write-Host "`n⚠️ 5. Création de la table Safety Incidents..." -ForegroundColor Yellow
php artisan make:migration create_safety_incidents_table

$safetyContent = @'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('safety_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('reporter_id')->constrained('users');
            $table->date('date');
            $table->enum('severity', ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'])->default('LOW');
            $table->text('description');
            $table->string('status')->default('OPEN');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('safety_incidents');
    }
};
'@
Write-MigrationContent -Pattern "*create_safety_incidents_table.php" -Content $safetyContent


# --- 6. MEDIA & PHOTOS ---
Write-Host "`n📸 6. Création de la table Media..." -ForegroundColor Yellow
php artisan make:migration create_media_table

$mediaContent = @'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_log_id')->nullable()->constrained('daily_logs')->onDelete('cascade');
            $table->foreignId('safety_incident_id')->nullable()->constrained('safety_incidents')->onDelete('cascade');
            $table->string('url');
            $table->enum('type', ['BEFORE', 'DURING', 'AFTER', 'DOCUMENT'])->default('DURING');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('media');
    }
};
'@
Write-MigrationContent -Pattern "*create_media_table.php" -Content $mediaContent


# --- 7. MATÉRIEL ---
Write-Host "`n🚜 7. Création de la table Equipment..." -ForegroundColor Yellow
php artisan make:migration create_equipment_table

$equipContent = @'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('serial_number')->nullable();
            $table->enum('status', ['AVAILABLE', 'IN_USE', 'MAINTENANCE', 'BROKEN'])->default('AVAILABLE');
            $table->foreignId('current_project_id')->nullable()->constrained('projects')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('equipment');
    }
};
'@
Write-MigrationContent -Pattern "*create_equipment_table.php" -Content $equipContent


# --- 8. EMAIL OTP ---
Write-Host "`n📧 8. Configuration de l'Email OTP..." -ForegroundColor Yellow
php artisan make:mail OtpCodeMail

$mailFile = "app/Mail/OtpCodeMail.php"
# Ici on rebascule en Double Quote car on a besoin d'interpolation pour la classe Mailable (seulement si besoin)
# Mais comme on ne met pas de variables dynamiques PowerShell dedans, on reste en Single Quote pour sécurité
$mailClassContent = @'
<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $code;

    public function __construct($code)
    {
        $this->code = $code;
    }

    public function build()
    {
        return $this->markdown('emails.otp')
                    ->subject('🔒 Code de sécurité MBC');
    }
}
'@
Set-Content $mailFile $mailClassContent


# Création dossier views
if (-not (Test-Path "resources/views/emails")) {
    New-Item -ItemType Directory -Force -Path "resources/views/emails" | Out-Null
}

$viewFile = "resources/views/emails/otp.blade.php"
$viewContent = @'
@component('mail::message')
# Authentification Sécurisée

Bonjour,

Voici votre code de vérification pour accéder à votre espace MBC.
Il garantit la sécurité de vos données de chantier.

@component('mail::panel')
# {{ $code }}
@endcomponent

Ce code est valide **10 minutes**.

Cordialement,<br>
L'équipe MBC
@endcomponent
'@
Set-Content $viewFile $viewContent


Write-Host "`n🎉 INSTALLATION TERMINÉE ET CORRIGÉE !" -ForegroundColor Cyan
Write-Host "👉 Lancez maintenant 'php artisan migrate'" -ForegroundColor Cyan
