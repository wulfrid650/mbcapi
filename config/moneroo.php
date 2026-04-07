<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Moneroo API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration pour l'intégration de Moneroo - passerelle de paiement
    | pour Mobile Money (Orange Money, MTN MoMo) et cartes bancaires
    |
    */

    'secretKey' => env('MONEROO_SECRET_KEY'),

    /*
    |--------------------------------------------------------------------------
    | API Base URL
    |--------------------------------------------------------------------------
    |
    | L'URL de base de l'API Moneroo
    |
    */
    'baseUrl' => env('MONEROO_BASE_URL', 'https://api.moneroo.io/v1'),

    /*
    |--------------------------------------------------------------------------
    | Devise par défaut
    |--------------------------------------------------------------------------
    |
    | La devise utilisée par défaut pour les paiements
    | XOF = Franc CFA BCEAO, XAF = Franc CFA BEAC
    |
    */
    'currency' => env('MONEROO_CURRENCY', 'XOF'),

    /*
    |--------------------------------------------------------------------------
    | Mode test
    |--------------------------------------------------------------------------
    |
    | Active le mode test pour les paiements
    |
    */
    'testMode' => env('MONEROO_TEST_MODE', true),

    /*
    |--------------------------------------------------------------------------
    | URL de callback
    |--------------------------------------------------------------------------
    |
    | L'URL où Moneroo enverra les notifications de webhook
    |
    */
    'callbackUrl' => env('MONEROO_CALLBACK_URL'),

    /*
    |--------------------------------------------------------------------------
    | URL de retour
    |--------------------------------------------------------------------------
    |
    | L'URL vers laquelle l'utilisateur sera redirigé après le paiement
    |
    */
    'returnUrl' => env('MONEROO_RETURN_URL'),

    /*
    |--------------------------------------------------------------------------
    | HTTP timeouts
    |--------------------------------------------------------------------------
    |
    | Timeouts used for Moneroo requests. We keep them configurable because
    | shared hosting environments are often slower and less predictable.
    |
    */
    'timeout' => (int) env('MONEROO_TIMEOUT', 20),
    'connectTimeout' => (int) env('MONEROO_CONNECT_TIMEOUT', 10),
    'retryTimes' => (int) env('MONEROO_RETRY_TIMES', 1),
    'retryDelayMs' => (int) env('MONEROO_RETRY_DELAY_MS', 500),

    /*
    |--------------------------------------------------------------------------
    | Pending enrollment window
    |--------------------------------------------------------------------------
    |
    | Delay during which a training enrollment remains pending payment before
    | being automatically cancelled if no successful webhook or staff manual
    | validation happens.
    |
    */
    'pendingEnrollmentWindowMinutes' => (int) env('MONEROO_PENDING_ENROLLMENT_WINDOW_MINUTES', 60),
];
