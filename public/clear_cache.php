<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

echo "<h1>Nettoyage du cache...</h1>";

try {
    Illuminate\Support\Facades\Artisan::call('optimize:clear');
    echo "<p style='color:green'>✔ php artisan optimize:clear : Succès</p>";
    echo "<pre>" . Illuminate\Support\Facades\Artisan::output() . "</pre>";
} catch (\Exception $e) {
    echo "<p style='color:red'>✘ Erreur : " . $e->getMessage() . "</p>";
}

echo "<p>Veuillez supprimer ce fichier (public/clear_cache.php) une fois terminé par sécurité.</p>";
