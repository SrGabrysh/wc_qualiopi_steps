<?php

namespace WcQualiopiSteps\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Nettoyer les options de test avant chaque test
        global $test_options;
        $test_options = array();
        
        // Nettoyer le cache des sessions WC mock
        if (function_exists('WC') && WC()->session) {
            WC()->session = new \MockWCSession();
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Nettoyer après chaque test
        global $test_options;
        $test_options = array();
    }

    /**
     * Helper pour créer un contexte de test
     * @deprecated Utiliser testContext() function globale à la place
     */
    protected function createTestContext(array $overrides = []): array
    {
        return testContext($overrides);
    }

    /**
     * Assert qu'une décision de checkout a les bonnes propriétés
     */
    protected function assertValidDecision($decision, bool $expectedAllow, string $expectedReason): void
    {
        $this->assertInstanceOf(\WcQualiopiSteps\Core\CheckoutDecision::class, $decision);
        $this->assertSame($expectedAllow, $decision->allow);
        $this->assertSame($expectedReason, $decision->reason);
    }

    /**
     * Assert qu'un token a le bon format
     */
    protected function assertValidToken(string $token): void
    {
        $this->assertIsString($token);
        $this->assertNotEmpty($token);
        $this->assertStringContainsString('.', $token);
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9._-]+$/', $token);
    }
}
