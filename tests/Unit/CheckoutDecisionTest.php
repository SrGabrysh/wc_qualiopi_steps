<?php

use WcQualiopiSteps\Core\CheckoutDecision;

describe('CheckoutDecision', function () {

    it('allows checkout when enforcement is disabled', function () {
        $context = testContext([
            'flags' => ['enforce_checkout' => false]
        ]);

        $decision = CheckoutDecision::decide($context);

        expect($decision->allow)->toBeTrue();
        expect($decision->reason)->toBe('flag_off');
        expect($decision->getMessage())->toContain('Enforcement désactivé');
    });

    it('allows checkout when no product in cart', function () {
        $context = testContext([
            'flags' => ['enforce_checkout' => true],
            'cart' => ['product_id' => null]
        ]);

        $decision = CheckoutDecision::decide($context);

        expect($decision->allow)->toBeTrue();
        expect($decision->reason)->toBe('no_product');
    });

    it('allows checkout when no active mapping', function () {
        $context = testContext([
            'flags' => ['enforce_checkout' => true],
            'cart' => ['product_id' => 123],
            'mapping' => ['active' => false]
        ]);

        $decision = CheckoutDecision::decide($context);

        expect($decision->allow)->toBeTrue();
        expect($decision->reason)->toBe('no_mapping');
        expect($decision->details['product_id'])->toBe(123);
    });

    it('allows checkout with valid temporary token', function () {
        $context = testContext([
            'flags' => ['enforce_checkout' => true],
            'cart' => ['product_id' => 123],
            'mapping' => ['active' => true],
            'query' => ['tp_token' => 'valid_token_123']
        ]);

        $decision = CheckoutDecision::decide($context);

        expect($decision->allow)->toBeTrue();
        expect($decision->reason)->toBe('temp_token');
        expect($decision->details['token'])->toBe('valid_token_123');
    });

    it('allows checkout when test is solved in session', function () {
        $context = testContext([
            'flags' => ['enforce_checkout' => true],
            'cart' => ['product_id' => 123],
            'mapping' => ['active' => true],
            'query' => ['tp_token' => null],
            'session' => ['solved' => [123 => true]]
        ]);

        $decision = CheckoutDecision::decide($context);

        expect($decision->allow)->toBeTrue();
        expect($decision->reason)->toBe('session_ok');
        expect($decision->details['product_id'])->toBe(123);
    });

    it('allows checkout when test is validated in usermeta', function () {
        $context = testContext([
            'flags' => ['enforce_checkout' => true],
            'cart' => ['product_id' => 123],
            'user' => ['id' => 42],
            'mapping' => ['active' => true],
            'query' => ['tp_token' => null],
            'session' => ['solved' => [123 => false]],
            'usermeta' => ['ok' => [123 => true]]
        ]);

        $decision = CheckoutDecision::decide($context);

        expect($decision->allow)->toBeTrue();
        expect($decision->reason)->toBe('usermeta_ok');
        expect($decision->details['product_id'])->toBe(123);
        expect($decision->details['user_id'])->toBe(42);
    });

    it('blocks checkout when no validation proof exists', function () {
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

        $decision = CheckoutDecision::decide($context);

        expect($decision->allow)->toBeFalse();
        expect($decision->reason)->toBe('no_validation');
        expect($decision->redirect_url)->toBe('/test-positionnement-123');
        expect($decision->details['message'])->toContain('Test de positionnement requis');
        expect($decision->details['product_id'])->toBe(123);
        expect($decision->details['user_id'])->toBe(42);
    });

    it('prioritizes temporary token over session validation', function () {
        $context = testContext([
            'flags' => ['enforce_checkout' => true],
            'cart' => ['product_id' => 123],
            'mapping' => ['active' => true],
            'query' => ['tp_token' => 'priority_token'],
            'session' => ['solved' => [123 => true]] // Session dit OK mais token prioritaire
        ]);

        $decision = CheckoutDecision::decide($context);

        expect($decision->allow)->toBeTrue();
        expect($decision->reason)->toBe('temp_token'); // Pas session_ok
        expect($decision->details['token'])->toBe('priority_token');
    });

    it('prioritizes session over usermeta validation', function () {
        $context = testContext([
            'flags' => ['enforce_checkout' => true],
            'cart' => ['product_id' => 123],
            'user' => ['id' => 42],
            'mapping' => ['active' => true],
            'query' => ['tp_token' => null],
            'session' => ['solved' => [123 => true]],
            'usermeta' => ['ok' => [123 => true]] // Les deux disent OK
        ]);

        $decision = CheckoutDecision::decide($context);

        expect($decision->allow)->toBeTrue();
        expect($decision->reason)->toBe('session_ok'); // Pas usermeta_ok
    });

    it('handles multiple products correctly', function () {
        // Test avec produit 456 non validé
        $context = testContext([
            'flags' => ['enforce_checkout' => true],
            'cart' => ['product_id' => 456],
            'mapping' => ['active' => true, 'test_page_url' => '/test-456'],
            'session' => ['solved' => [123 => true, 456 => false]], // 123 OK, 456 KO
            'usermeta' => ['ok' => [123 => true, 456 => false]]
        ]);

        $decision = CheckoutDecision::decide($context);

        expect($decision->allow)->toBeFalse();
        expect($decision->reason)->toBe('no_validation');
        expect($decision->details['product_id'])->toBe(456);
    });

    it('ignores usermeta validation for anonymous users', function () {
        $context = testContext([
            'flags' => ['enforce_checkout' => true],
            'cart' => ['product_id' => 123],
            'user' => ['id' => 0], // Utilisateur anonyme
            'mapping' => ['active' => true, 'test_page_url' => '/test-123'],
            'query' => ['tp_token' => null],
            'session' => ['solved' => [123 => false]],
            'usermeta' => ['ok' => [123 => true]] // Devrait être ignoré
        ]);

        $decision = CheckoutDecision::decide($context);

        expect($decision->allow)->toBeFalse();
        expect($decision->reason)->toBe('no_validation');
    });

    it('provides helper methods for decision checking', function () {
        $allow_decision = CheckoutDecision::allow('test_reason');
        $block_decision = CheckoutDecision::block('test_reason', '/redirect');

        expect($allow_decision->isAllowed())->toBeTrue();
        expect($allow_decision->isBlocked())->toBeFalse();

        expect($block_decision->isAllowed())->toBeFalse();
        expect($block_decision->isBlocked())->toBeTrue();
    });

    it('converts to array for logging', function () {
        $context = testContext([
            'flags' => ['enforce_checkout' => true],
            'cart' => ['product_id' => 123],
            'mapping' => ['active' => true, 'test_page_url' => '/test-123'],
            'session' => ['solved' => [123 => false]]
        ]);

        $decision = CheckoutDecision::decide($context);
        $array = $decision->toArray();

        expect($array)
            ->toBeArray()
            ->toHaveKey('allow', false)
            ->toHaveKey('reason', 'no_validation')
            ->toHaveKey('redirect_url', '/test-123')
            ->toHaveKey('details');

        expect($array['details'])
            ->toHaveKey('message')
            ->toHaveKey('product_id', 123);
    });

});
