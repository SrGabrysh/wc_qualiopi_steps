<?php

use WcQualiopiSteps\Core\Plugin;

/**
 * Tests unitaires pour la classe Plugin
 * 
 * Ces tests couvrent la logique critique de gestion des flags,
 * le pattern singleton et les constantes du plugin.
 */
describe('Plugin', function () {
    
    beforeEach(function () {
        // Nettoyer les options avant chaque test
        delete_option('wcqs_flags');
    });
    
    afterEach(function () {
        // Nettoyer après chaque test
        delete_option('wcqs_flags');
    });

    describe('Constants and Defaults', function () {
        
        it('defines correct version constant', function () {
            expect(Plugin::VERSION)->toBeString();
            expect(Plugin::VERSION)->toMatch('/^\d+\.\d+\.\d+$/'); // Format x.y.z
        });

        it('defines default flags correctly', function () {
            $defaults = Plugin::DEFAULT_FLAGS;
            
            expect($defaults)
                ->toBeArray()
                ->toHaveKey('enforce_cart')
                ->toHaveKey('enforce_checkout') 
                ->toHaveKey('logging');
                
            expect($defaults['enforce_cart'])->toBeFalse();
            expect($defaults['enforce_checkout'])->toBeFalse();
            expect($defaults['logging'])->toBeTrue();
        });
    });

    describe('Flag Management', function () {
        
        it('returns default flags when no options stored', function () {
            $flags = Plugin::get_flags();
            
            expect($flags)
                ->toBeArray()
                ->toHaveKey('enforce_cart', false)
                ->toHaveKey('enforce_checkout', false)
                ->toHaveKey('logging', true);
        });

        it('merges stored flags with defaults', function () {
            // Stocker seulement une partie des flags
            update_option('wcqs_flags', ['enforce_cart' => true]);
            
            $flags = Plugin::get_flags();
            
            expect($flags)
                ->toHaveKey('enforce_cart', true)      // Stocké
                ->toHaveKey('enforce_checkout', false) // Défaut
                ->toHaveKey('logging', true);          // Défaut
        });

        it('returns specific flag when requested', function () {
            update_option('wcqs_flags', ['enforce_cart' => true]);
            
            expect(Plugin::get_flags('enforce_cart'))->toBeTrue();
            expect(Plugin::get_flags('enforce_checkout'))->toBeFalse();
            expect(Plugin::get_flags('logging'))->toBeTrue();
        });

        it('returns null for non-existent flag', function () {
            expect(Plugin::get_flags('non_existent_flag'))->toBeNull();
        });

        it('sets flag correctly', function () {
            $result = Plugin::set_flag('enforce_cart', true);
            
            expect($result)->toBeTrue();
            expect(Plugin::get_flags('enforce_cart'))->toBeTrue();
        });

        it('updates existing flag', function () {
            Plugin::set_flag('enforce_cart', true);
            Plugin::set_flag('enforce_cart', false);
            
            expect(Plugin::get_flags('enforce_cart'))->toBeFalse();
        });

        it('preserves other flags when setting one flag', function () {
            Plugin::set_flag('enforce_cart', true);
            Plugin::set_flag('logging', false);
            
            $flags = Plugin::get_flags();
            
            expect($flags)
                ->toHaveKey('enforce_cart', true)
                ->toHaveKey('enforce_checkout', false) // Inchangé
                ->toHaveKey('logging', false);
        });
    });

    describe('Singleton Pattern', function () {
        
        it('returns same instance on multiple calls', function () {
            $instance1 = Plugin::get_instance();
            $instance2 = Plugin::get_instance();
            
            expect($instance1)->toBe($instance2);
            expect($instance1)->toBeInstanceOf(Plugin::class);
        });
        
        it('prevents direct instantiation', function () {
            $reflection = new ReflectionClass(Plugin::class);
            $constructor = $reflection->getConstructor();
            
            expect($constructor->isPrivate())->toBeTrue();
        });
    });

    describe('Edge Cases', function () {
        
        it('handles corrupted flag options gracefully', function () {
            // Simuler des données corrompues
            update_option('wcqs_flags', 'invalid_data');
            
            $flags = Plugin::get_flags();
            
            // Devrait revenir aux défauts
            expect($flags)
                ->toBeArray()
                ->toHaveKey('enforce_cart', false)
                ->toHaveKey('enforce_checkout', false)
                ->toHaveKey('logging', true);
        });

        it('handles null flag values', function () {
            Plugin::set_flag('enforce_cart', null);
            
            expect(Plugin::get_flags('enforce_cart'))->toBeNull();
        });

        it('handles empty flag name', function () {
            expect(Plugin::get_flags(''))->toBeNull();
        });
    });
});
