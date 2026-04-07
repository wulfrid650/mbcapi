<?php
/**
 * Script de nettoyage du cache CORS
 * Exécutez ce fichier via URL après déploiement: https://apgw.madibabc.com/clear_cors_cache.php
 * 
 * SUPPRIMEZ CE FICHIER APRÈS UTILISATION POUR LA SÉCURITÉ
 */

// Sécurité basique - supprimez cette ligne si vous êtes sûr
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
    die('Ajoutez ?confirm=yes à l\'URL pour exécuter ce script');
}

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "<pre>";
echo "=== Nettoyage du cache Laravel ===\n\n";

// Vider les caches
try {
    Artisan::call('config:clear');
    echo "✓ Config cache cleared\n";
    echo Artisan::output();
} catch (Exception $e) {
    echo "✗ Error clearing config: " . $e->getMessage() . "\n";
}

try {
    Artisan::call('cache:clear');
    echo "✓ Application cache cleared\n";
    echo Artisan::output();
} catch (Exception $e) {
    echo "✗ Error clearing cache: " . $e->getMessage() . "\n";
}

try {
    Artisan::call('route:clear');
    echo "✓ Route cache cleared\n";
    echo Artisan::output();
} catch (Exception $e) {
    echo "✗ Error clearing routes: " . $e->getMessage() . "\n";
}

// Afficher la configuration CORS actuelle
echo "\n=== Configuration CORS actuelle ===\n";
$corsConfig = config('cors');
print_r($corsConfig);

// Test CORS headers
echo "\n=== Test des en-têtes CORS ===\n";
$testOrigin = 'https://madibabc.com';
$allowed = in_array($testOrigin, $corsConfig['allowed_origins'] ?? []);
echo "Origin '$testOrigin' autorisé: " . ($allowed ? 'OUI' : 'NON (vérifiez les patterns)') . "\n";

// Vérifier les patterns
foreach ($corsConfig['allowed_origins_patterns'] ?? [] as $pattern) {
    if (preg_match($pattern, $testOrigin)) {
        echo "✓ Matche le pattern: $pattern\n";
    }
}

echo "\n=== TERMINÉ ===\n";
echo "⚠️  SUPPRIMEZ CE FICHIER APRÈS UTILISATION!\n";
echo "</pre>";
