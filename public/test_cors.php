<?php
/**
 * Script de test CORS
 * URL: https://apgw.madibabc.com/test_cors.php
 * 
 * Ce script teste si les headers CORS sont correctement configurés
 */

// Force les headers CORS pour ce test
header('Access-Control-Allow-Origin: https://madibabc.com');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin, X-XSRF-TOKEN');
header('Access-Control-Max-Age: 86400');

// Si c'est une requête OPTIONS, répondre immédiatement
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Tester la configuration
header('Content-Type: application/json');

$diagnostics = [
    'success' => true,
    'message' => 'CORS fonctionne correctement',
    'server_info' => [
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
        'origin' => $_SERVER['HTTP_ORIGIN'] ?? 'N/A',
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
    ],
    'headers_sent' => [
        'Access-Control-Allow-Origin' => 'https://madibabc.com',
        'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-XSRF-TOKEN',
    ],
    'mod_headers_available' => function_exists('apache_get_modules') ? in_array('mod_headers', apache_get_modules()) : 'Unknown',
    'htaccess_location' => __DIR__ . '/.htaccess',
    'htaccess_exists' => file_exists(__DIR__ . '/.htaccess'),
    'htaccess_readable' => is_readable(__DIR__ . '/.htaccess'),
];

if ($diagnostics['htaccess_exists']) {
    $diagnostics['htaccess_content_preview'] = substr(file_get_contents(__DIR__ . '/.htaccess'), 0, 500);
}

echo json_encode($diagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

// Instructions
echo "\n\n/* 
INSTRUCTIONS:

1. Ouvrez la console de votre navigateur sur https://madibabc.com
2. Exécutez ce code JavaScript:

fetch('https://apgw.madibabc.com/test_cors.php')
  .then(r => r.json())
  .then(data => {
    console.log('✅ CORS fonctionne!', data);
  })
  .catch(err => {
    console.error('❌ CORS ne fonctionne pas:', err);
  });

3. Si vous voyez \"CORS fonctionne!\", le problème est résolu.
4. Si vous voyez une erreur CORS, vérifiez que mod_headers est activé:
   sudo a2enmod headers
   sudo systemctl restart apache2

5. SUPPRIMEZ CE FICHIER après le test pour la sécurité.
*/";
