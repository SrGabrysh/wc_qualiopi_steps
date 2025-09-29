<?php

use WcQualiopiSteps\Security\WCQS_Token;
use WcQualiopiSteps\Utils\WCQS_Session;

describe('Token Session Integration', function () {

    beforeEach(function () {
        // Nettoyer avant chaque test
        WCQS_Token::clear_cache();
        WCQS_Session::cleanup_expired();
    });

    it('creates token for validated session user', function () {
        $user_id = 123;
        $product_id = 456;

        // 1. Marquer session comme résolue
        WCQS_Session::set_solved($product_id);
        expect(WCQS_Session::is_solved($product_id))->toBeTrue();

        // 2. Créer token pour cet utilisateur/produit
        $token = WCQS_Token::create($user_id, $product_id);
        expect($token)->toBeValidToken();

        // 3. Vérifier token
        $result = WCQS_Token::verify($token, $user_id, $product_id);
        expect($result)->toBeArray()
            ->toHaveKey('user_id', $user_id)
            ->toHaveKey('product_id', $product_id);

        // 4. Intégration: token ET session valides
        expect(WCQS_Session::is_solved($product_id))->toBeTrue();
        expect($result)->not()->toBeFalse();
    });

    it('handles token expiration with session cleanup', function () {
        $user_id = 789;
        $product_id = 101;

        // 1. Créer token avec TTL très court
        $old_timestamp = time() - 8000; // 2h+ dans le passé
        $token = WCQS_Token::create($user_id, $product_id, $old_timestamp);

        // 2. Session avec TTL court aussi
        WCQS_Session::set_solved($product_id, 1); // 1 seconde

        // 3. Attendre expiration
        sleep(2);

        // 4. Vérifications d'intégration
        expect(WCQS_Token::verify($token, $user_id, $product_id))->toBeFalse();
        expect(WCQS_Session::is_solved($product_id))->toBeFalse();

        // 5. Cleanup des sessions expirées
        $cleaned = WCQS_Session::cleanup_expired();
        expect($cleaned)->toBeGreaterThanOrEqual(1);
    });

    it('supports token rotation with persistent sessions', function () {
        $user_id = 555;
        $product_id = 666;

        // 1. Session longue durée
        WCQS_Session::set_solved($product_id, 3600); // 1 heure

        // 2. Token initial
        $token1 = WCQS_Token::create($user_id, $product_id);
        $version1 = WCQS_Token::get_key_version();

        // 3. Rotation de clé
        WCQS_Token::rotate_key();
        $version2 = WCQS_Token::get_key_version();

        // 4. Nouveau token après rotation
        $token2 = WCQS_Token::create($user_id, $product_id);

        // 5. Vérifications d'intégration
        expect($version2)->toBeGreaterThan($version1);
        expect($token2)->not()->toBe($token1);

        // Ancien token fonctionne encore (clé précédente)
        expect(WCQS_Token::verify($token1, $user_id, $product_id))->toBeArray();
        
        // Nouveau token fonctionne
        expect(WCQS_Token::verify($token2, $user_id, $product_id))->toBeArray();

        // Session toujours active
        expect(WCQS_Session::is_solved($product_id))->toBeTrue();
    });

    it('validates complete user journey flow', function () {
        $user_id = 999;
        $product_id = 888;

        // === Étape 1: Utilisateur arrive, pas de validation ===
        expect(WCQS_Session::is_solved($product_id))->toBeFalse();

        // === Étape 2: Utilisateur fait le test, obtient un token ===
        $validation_token = WCQS_Token::create($user_id, $product_id);
        expect($validation_token)->toBeValidToken();

        // === Étape 3: Token validé, session marquée comme résolue ===
        $token_data = WCQS_Token::verify($validation_token, $user_id, $product_id);
        expect($token_data)->toBeArray();

        // Marquer session comme résolue (simule validation côté serveur)
        WCQS_Session::set_solved($product_id);

        // === Étape 4: Vérifications finales ===
        expect(WCQS_Session::is_solved($product_id))->toBeTrue();
        
        $session_details = WCQS_Session::get_session_details($product_id);
        expect($session_details)
            ->toHaveKey('solved', true)
            ->toHaveKey('is_expired', false);

        // === Étape 5: Token peut être utilisé pour checkout ===
        $checkout_token = WCQS_Token::create($user_id, $product_id);
        $checkout_data = WCQS_Token::verify($checkout_token, $user_id, $product_id);
        
        expect($checkout_data)->toBeArray()
            ->toHaveKey('user_id', $user_id)
            ->toHaveKey('product_id', $product_id);
    });

    it('handles concurrent user sessions correctly', function () {
        $product_id = 777;
        
        // Plusieurs utilisateurs pour le même produit
        $users = [101, 102, 103];
        $tokens = [];

        // 1. Créer sessions et tokens pour chaque utilisateur
        foreach ($users as $user_id) {
            WCQS_Session::set_solved($product_id); // Session globale par produit
            $tokens[$user_id] = WCQS_Token::create($user_id, $product_id);
        }

        // 2. Vérifier que chaque token est valide pour son utilisateur
        foreach ($users as $user_id) {
            $result = WCQS_Token::verify($tokens[$user_id], $user_id, $product_id);
            expect($result)->toBeArray()
                ->toHaveKey('user_id', $user_id)
                ->toHaveKey('product_id', $product_id);
        }

        // 3. Vérifier que les tokens ne sont pas interchangeables
        expect(WCQS_Token::verify($tokens[101], 102, $product_id))->toBeFalse();
        expect(WCQS_Token::verify($tokens[102], 103, $product_id))->toBeFalse();

        // 4. Session commune reste active
        expect(WCQS_Session::is_solved($product_id))->toBeTrue();
    });

});
