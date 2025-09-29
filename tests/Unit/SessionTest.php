<?php

use WcQualiopiSteps\Utils\WCQS_Session;

describe('WCQS_Session', function () {
    
    beforeEach(function () {
        // Mock WooCommerce session si pas disponible
        if (!function_exists('WC')) {
            test()->markTestSkipped('WooCommerce not available for session tests');
        }
    });

    afterEach(function () {
        // Nettoyer les sessions de test si possible
        if (function_exists('WC') && WC()->session) {
            $test_products = [123, 456, 789, 101, 201, 202, 203, 301, 302, 303, 401, 501, 502, 503, 601, 602, 603, 999];
            
            foreach ($test_products as $product_id) {
                WCQS_Session::unset_solved($product_id);
            }
        }
    });

    it('sets and checks solved status', function () {
        $product_id = 123;

        // Initialement non résolu
        expect(WCQS_Session::is_solved($product_id))->toBeFalse();

        // Marquer comme résolu
        $result = WCQS_Session::set_solved($product_id);
        expect($result)->toBeTrue();

        // Vérifier qu'il est maintenant résolu
        expect(WCQS_Session::is_solved($product_id))->toBeTrue();
    });

    it('respects custom TTL', function () {
        $product_id = 456;
        $short_ttl = 1; // 1 seconde

        // Marquer comme résolu avec TTL court
        WCQS_Session::set_solved($product_id, $short_ttl);
        expect(WCQS_Session::is_solved($product_id))->toBeTrue();

        // Attendre expiration
        sleep(2);

        // Doit être expiré maintenant
        expect(WCQS_Session::is_solved($product_id))->toBeFalse();
    });

    it('can unset solved status', function () {
        $product_id = 789;

        // Marquer comme résolu
        WCQS_Session::set_solved($product_id);
        expect(WCQS_Session::is_solved($product_id))->toBeTrue();

        // Désactiver
        $result = WCQS_Session::unset_solved($product_id);
        expect($result)->toBeTrue();

        // Vérifier qu'il n'est plus résolu
        expect(WCQS_Session::is_solved($product_id))->toBeFalse();
    });

    it('provides detailed session information', function () {
        $product_id = 101;

        // Pas de détails initialement
        $details = WCQS_Session::get_session_details($product_id);
        expect($details)->toBeNull();

        // Marquer comme résolu
        WCQS_Session::set_solved($product_id);

        // Obtenir détails
        $details = WCQS_Session::get_session_details($product_id);
        
        expect($details)
            ->toBeArray()
            ->toHaveKey('solved', true)
            ->toHaveKey('timestamp')
            ->toHaveKey('expires')
            ->toHaveKey('is_expired', false)
            ->toHaveKey('age')
            ->toHaveKey('remaining_ttl');
            
        expect($details['timestamp'])->toBeInt();
        expect($details['expires'])->toBeInt();
    });

    it('cleans up expired sessions', function () {
        // Créer plusieurs sessions avec TTL différents
        WCQS_Session::set_solved(201, 3600); // Valide
        WCQS_Session::set_solved(202, 1);    // Expire rapidement
        WCQS_Session::set_solved(203, 3600); // Valide

        // Attendre expiration
        sleep(2);

        // Nettoyer
        $cleaned = WCQS_Session::cleanup_expired();
        expect($cleaned)->toBeGreaterThanOrEqual(1);

        // Vérifier état
        expect(WCQS_Session::is_solved(201))->toBeTrue();
        expect(WCQS_Session::is_solved(202))->toBeFalse();
        expect(WCQS_Session::is_solved(203))->toBeTrue();
    });

    it('lists all active sessions', function () {
        // Nettoyer d'abord
        WCQS_Session::cleanup_expired();

        // Créer sessions actives
        WCQS_Session::set_solved(301);
        WCQS_Session::set_solved(302);
        WCQS_Session::set_solved(303, 1); // Expire rapidement

        $active_sessions = WCQS_Session::get_all_active_sessions();
        
        expect($active_sessions)
            ->toBeArray()
            ->toHaveKey(301)
            ->toHaveKey(302)
            ->toHaveKey(303);

        // Attendre expiration d'une session
        sleep(2);

        $active_sessions = WCQS_Session::get_all_active_sessions();
        
        expect($active_sessions)
            ->toHaveKey(301)
            ->toHaveKey(302)
            ->not()->toHaveKey(303);
    });

    it('can extend existing sessions', function () {
        $product_id = 401;

        // Marquer avec TTL court
        WCQS_Session::set_solved($product_id, 2);
        
        $details1 = WCQS_Session::get_session_details($product_id);
        $initial_expires = $details1['expires'];

        // Prolonger
        $result = WCQS_Session::extend_session($product_id, 3600);
        expect($result)->toBeTrue();

        $details2 = WCQS_Session::get_session_details($product_id);
        $new_expires = $details2['expires'];

        expect($new_expires)->toBeGreaterThan($initial_expires);
    });

    it('cannot extend nonexistent sessions', function () {
        $product_id = 999;

        $result = WCQS_Session::extend_session($product_id);
        expect($result)->toBeFalse();
    });

    it('provides session statistics', function () {
        // Nettoyer d'abord
        WCQS_Session::cleanup_expired();

        // Créer sessions de test
        WCQS_Session::set_solved(501, 3600); // Active
        WCQS_Session::set_solved(502, 3600); // Active
        WCQS_Session::set_solved(503, 1);    // Expire rapidement

        sleep(2); // Laisser 503 expirer

        $stats = WCQS_Session::get_session_stats();
        
        expect($stats)
            ->toBeArray()
            ->toHaveKey('total')
            ->toHaveKey('active')
            ->toHaveKey('expired');

        expect($stats['total'])->toBeGreaterThanOrEqual(3);
        expect($stats['active'])->toBeGreaterThanOrEqual(2);
        expect($stats['expired'])->toBeGreaterThanOrEqual(1);
    });

    it('handles multiple products independently', function () {
        $products = [601, 602, 603];

        // Marquer tous comme résolus
        foreach ($products as $product_id) {
            WCQS_Session::set_solved($product_id);
        }

        // Vérifier tous
        foreach ($products as $product_id) {
            expect(WCQS_Session::is_solved($product_id))->toBeTrue();
        }

        // Désactiver un seul
        WCQS_Session::unset_solved(602);

        // Vérifier états
        expect(WCQS_Session::is_solved(601))->toBeTrue();
        expect(WCQS_Session::is_solved(602))->toBeFalse();
        expect(WCQS_Session::is_solved(603))->toBeTrue();
    });

});