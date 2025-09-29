<?php

use WcQualiopiSteps\Security\WCQS_Token;

describe('WCQS_Token', function () {
    
    beforeEach(function () {
        // Vider le cache avant chaque test
        WCQS_Token::clear_cache();
        
        // Nettoyer les options de test
        delete_option('wcqs_hmac_secret');
        delete_option('wcqs_hmac_secret_prev');
        delete_option('wcqs_hmac_key_version');
    });

    afterEach(function () {
        // Nettoyer après chaque test
        WCQS_Token::clear_cache();
        delete_option('wcqs_hmac_secret');
        delete_option('wcqs_hmac_secret_prev');
        delete_option('wcqs_hmac_key_version');
    });

    it('creates and verifies a valid token', function () {
        $user_id = 123;
        $product_id = 456;
        $timestamp = time();
        $nonce = 'test_nonce';

        // Créer token
        $token = WCQS_Token::create($user_id, $product_id, $timestamp, $nonce);
        
        expect($token)
            ->toBeString()
            ->toContain('.');

        // Vérifier token
        $result = WCQS_Token::verify($token, $user_id, $product_id);
        
        expect($result)
            ->toBeArray()
            ->toHaveKey('user_id', $user_id)
            ->toHaveKey('product_id', $product_id)
            ->toHaveKey('timestamp', $timestamp)
            ->toHaveKey('nonce', $nonce)
            ->toHaveKey('age');
    });

    it('creates token with default parameters', function () {
        $user_id = 789;
        $product_id = 101;

        $token = WCQS_Token::create($user_id, $product_id);
        
        expect($token)->toBeString();

        $result = WCQS_Token::verify($token, $user_id, $product_id);
        
        expect($result)
            ->toBeArray()
            ->toHaveKey('user_id', $user_id)
            ->toHaveKey('product_id', $product_id);
            
        expect($result['timestamp'])->toBeInt();
        expect($result['nonce'])->toBeString();
    });

    it('rejects expired tokens', function () {
        $user_id = 111;
        $product_id = 222;
        $old_timestamp = time() - 8000; // Plus de 2h

        $token = WCQS_Token::create($user_id, $product_id, $old_timestamp);
        
        // Vérification avec TTL par défaut (2h) - doit échouer
        $result = WCQS_Token::verify($token, $user_id, $product_id);
        expect($result)->toBeFalse();

        // Vérification avec TTL plus long - doit réussir
        $result = WCQS_Token::verify($token, $user_id, $product_id, 10000);
        expect($result)->toBeArray();
    });

    it('rejects token with wrong user_id', function () {
        $user_id = 333;
        $product_id = 444;

        $token = WCQS_Token::create($user_id, $product_id);
        
        // Vérifier avec mauvais user_id
        $result = WCQS_Token::verify($token, 999, $product_id);
        expect($result)->toBeFalse();
    });

    it('rejects token with wrong product_id', function () {
        $user_id = 555;
        $product_id = 666;

        $token = WCQS_Token::create($user_id, $product_id);
        
        // Vérifier avec mauvais product_id
        $result = WCQS_Token::verify($token, $user_id, 999);
        expect($result)->toBeFalse();
    });

    it('rejects corrupted tokens', function () {
        $user_id = 777;
        $product_id = 888;

        $token = WCQS_Token::create($user_id, $product_id);
        
        // Corrompre le token
        $corrupted_token = substr($token, 0, -5) . 'xxxxx';
        
        $result = WCQS_Token::verify($corrupted_token, $user_id, $product_id);
        expect($result)->toBeFalse();
    });

    it('rejects malformed tokens', function () {
        $user_id = 999;
        $product_id = 111;

        // Token sans point
        $result = WCQS_Token::verify('invalidtoken', $user_id, $product_id);
        expect($result)->toBeFalse();

        // Token avec trop de parties
        $result = WCQS_Token::verify('part1.part2.part3', $user_id, $product_id);
        expect($result)->toBeFalse();
    });

    it('handles key rotation correctly', function () {
        $user_id = 123;
        $product_id = 456;

        // Créer token avec clé actuelle
        $token1 = WCQS_Token::create($user_id, $product_id);
        $version1 = WCQS_Token::get_key_version();

        // Vérifier token
        $result = WCQS_Token::verify($token1, $user_id, $product_id);
        expect($result)->toBeArray();

        // Effectuer rotation
        $rotation_result = WCQS_Token::rotate_key();
        expect($rotation_result)->toBeTrue();

        $version2 = WCQS_Token::get_key_version();
        expect($version2)->toBeGreaterThan($version1);

        // L'ancien token doit encore fonctionner (clé précédente)
        $result = WCQS_Token::verify($token1, $user_id, $product_id);
        expect($result)->toBeArray();

        // Nouveau token avec nouvelle clé
        $token2 = WCQS_Token::create($user_id, $product_id);
        $result = WCQS_Token::verify($token2, $user_id, $product_id);
        expect($result)->toBeArray();

        // Les deux tokens sont différents
        expect($token2)->not()->toBe($token1);
    });

    it('uses base64url encoding', function () {
        $user_id = 123;
        $product_id = 456;

        $token = WCQS_Token::create($user_id, $product_id);
        
        // Le token ne doit pas contenir de caractères non URL-safe
        expect($token)
            ->not()->toContain('+')
            ->not()->toContain('/')
            ->not()->toContain('=');
    });

    it('generates incremental key versions', function () {
        // Version initiale
        $version1 = WCQS_Token::get_key_version();
        
        expect($version1)
            ->toBeInt()
            ->toBeGreaterThanOrEqual(1);

        // Après rotation
        WCQS_Token::rotate_key();
        $version2 = WCQS_Token::get_key_version();
        
        expect($version2)->toBe($version1 + 1);
    });

});