<?php

use WcQualiopiSteps\Core\CheckoutDecision;

describe('Checkout Flow Integration', function () {

    it('integrates checkout decision with cart guard logic', function () {
        // Test d'intégration qui simule le flow complet
        
        // 1. Contexte utilisateur sans validation
        $context = testContext([
            'flags' => ['enforce_checkout' => true],
            'cart' => ['product_id' => 123],
            'user' => ['id' => 42],
            'mapping' => [
                'active' => true,
                'test_page_url' => '/test-positionnement-123'
            ],
            'query' => ['tp_token' => null],
            'session' => ['solved' => [123 => false]],
            'usermeta' => ['ok' => [123 => false]]
        ]);

        // 2. Décision de checkout
        $decision = CheckoutDecision::decide($context);

        // 3. Vérifications d'intégration
        expect($decision->isBlocked())->toBeTrue();
        expect($decision->redirect_url)->not()->toBeNull();
        expect($decision->reason)->toBe('no_validation');

        // 4. Simulation du comportement Cart_Guard
        if ($decision->isBlocked()) {
            $redirect_url = $decision->redirect_url;
            $block_message = $decision->getMessage();
            
            expect($redirect_url)->toContain('/test-positionnement');
            expect($block_message)->toContain('Test de positionnement requis');
        }
    });

    it('allows checkout after successful validation', function () {
        // Simulation d'un utilisateur qui a validé le test
        
        $context = testContext([
            'flags' => ['enforce_checkout' => true],
            'cart' => ['product_id' => 123],
            'user' => ['id' => 42],
            'mapping' => ['active' => true],
            'session' => ['solved' => [123 => true]] // Validé en session
        ]);

        $decision = CheckoutDecision::decide($context);

        expect($decision->isAllowed())->toBeTrue();
        expect($decision->reason)->toBe('session_ok');
        expect($decision->redirect_url)->toBeNull();
    });

    it('handles token-based validation flow', function () {
        // Simulation d'un retour de test avec token temporaire
        
        $context = testContext([
            'flags' => ['enforce_checkout' => true],
            'cart' => ['product_id' => 123],
            'user' => ['id' => 42],
            'mapping' => ['active' => true],
            'query' => ['tp_token' => 'valid_temp_token_abc123']
        ]);

        $decision = CheckoutDecision::decide($context);

        expect($decision->isAllowed())->toBeTrue();
        expect($decision->reason)->toBe('temp_token');
        expect($decision->details['token'])->toBe('valid_temp_token_abc123');
    });

    it('respects enforcement flag configuration', function () {
        // Test avec enforcement désactivé
        
        $context = testContext([
            'flags' => ['enforce_checkout' => false], // Désactivé
            'cart' => ['product_id' => 123],
            'session' => ['solved' => [123 => false]] // Pas validé
        ]);

        $decision = CheckoutDecision::decide($context);

        expect($decision->isAllowed())->toBeTrue();
        expect($decision->reason)->toBe('flag_off');
    });

    it('handles multiple products in cart correctly', function () {
        // Simulation panier avec plusieurs produits
        
        // Produit 123: validé, Produit 456: non validé
        $context_product_456 = testContext([
            'flags' => ['enforce_checkout' => true],
            'cart' => ['product_id' => 456], // Produit différent
            'user' => ['id' => 42],
            'mapping' => ['active' => true, 'test_page_url' => '/test-456'],
            'session' => [
                'solved' => [
                    123 => true,  // Autre produit validé
                    456 => false  // Ce produit non validé
                ]
            ]
        ]);

        $decision = CheckoutDecision::decide($context_product_456);

        expect($decision->isBlocked())->toBeTrue();
        expect($decision->details['product_id'])->toBe(456);
        expect($decision->redirect_url)->toContain('/test-456');
    });

});
