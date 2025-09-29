<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Configuration Pest pour WC Qualiopi Steps
| Définit les classes de base et helpers pour tous les tests
|
*/

uses(
    WcQualiopiSteps\Tests\TestCase::class
)->in('Unit', 'Integration');

/*
|--------------------------------------------------------------------------
| Expectations personnalisées
|--------------------------------------------------------------------------
|
| Extensions des assertions Pest spécifiques au plugin
|
*/

expect()->extend('toBeValidToken', function () {
    return $this->toBeString()
        ->toContain('.')
        ->toMatch('/^[a-zA-Z0-9._-]+$/');
});

expect()->extend('toBeValidDecision', function () {
    return $this->toBeInstanceOf(WcQualiopiSteps\Core\CheckoutDecision::class)
        ->toHaveProperty('allow')
        ->toHaveProperty('reason');
});

/*
|--------------------------------------------------------------------------
| Helpers globaux
|--------------------------------------------------------------------------
|
| Fonctions utilitaires disponibles dans tous les tests
|
*/

/**
 * Créer un contexte de test pour CheckoutDecision
 */
function testContext(array $overrides = []): array
{
    $defaults = [
        'flags' => [
            'enforce_checkout' => false,
            'logging' => true
        ],
        'cart' => [
            'product_id' => 123
        ],
        'user' => [
            'id' => 42
        ],
        'query' => [
            'tp_token' => null
        ],
        'session' => [
            'solved' => [123 => false]
        ],
        'usermeta' => [
            'ok' => [123 => false]
        ],
        'mapping' => [
            'active' => true,
            'test_page_url' => '/test-123'
        ]
    ];

    // Utiliser array_replace_recursive pour éviter les problèmes avec les valeurs null
    return array_replace_recursive($defaults, $overrides);
}

/**
 * Simuler un timestamp fixe pour les tests
 */
function fixedTime(string $datetime = '2024-01-01 12:00:00'): int
{
    return strtotime($datetime);
}
