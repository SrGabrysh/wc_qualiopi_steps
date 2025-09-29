<?php

use WcQualiopiSteps\Utils\WCQS_Mapping;

describe('WCQS_Mapping', function () {

    beforeEach(function () {
        // Nettoyer le cache avant chaque test
        WCQS_Mapping::clear_cache();
        
        // Initialiser avec un mapping de test
        $this->setOption('wcqs_testpos_mapping', [
            '_version' => 1,
            'product_123' => [
                'page_id' => 456,
                'form_source' => 'learndash',
                'form_ref' => '789',
                'active' => true,
                'notes' => 'Test de positionnement Formation A'
            ],
            'product_124' => [
                'page_id' => 457,
                'form_source' => 'gravityforms',
                'form_ref' => '5',
                'active' => false,
                'notes' => 'Formation B (désactivée)'
            ],
            'product_125' => [
                'page_id' => 0, // Page manquante
                'active' => true
            ]
        ]);
    });

    afterEach(function () {
        WCQS_Mapping::clear_cache();
        delete_option('wcqs_testpos_mapping');
    });

    it('gets complete mapping with cache', function () {
        $mapping1 = WCQS_Mapping::get_mapping();
        $mapping2 = WCQS_Mapping::get_mapping(); // Deuxième appel depuis cache

        expect($mapping1)
            ->toBeArray()
            ->toHaveKey('_version', 1)
            ->toHaveKey('product_123')
            ->toHaveKey('product_124');

        expect($mapping2)->toBe($mapping1); // Même référence = cache utilisé
    });

    it('gets configuration for specific product', function () {
        $config = WCQS_Mapping::get_for_product(123);

        expect($config)
            ->toBeArray()
            ->toHaveKey('product_id', 123)
            ->toHaveKey('page_id', 456)
            ->toHaveKey('form_source', 'learndash')
            ->toHaveKey('form_ref', '789')
            ->toHaveKey('active', true)
            ->toHaveKey('notes');
    });

    it('returns null for non-existent product', function () {
        $config = WCQS_Mapping::get_for_product(999);
        expect($config)->toBeNull();
    });

    it('applies default values to product configuration', function () {
        // Produit avec configuration minimale
        $this->setOption('wcqs_testpos_mapping', [
            '_version' => 1,
            'product_100' => [
                'page_id' => 200
            ]
        ]);
        WCQS_Mapping::clear_cache();

        $config = WCQS_Mapping::get_for_product(100);

        expect($config)
            ->toHaveKey('product_id', 100)
            ->toHaveKey('page_id', 200)
            ->toHaveKey('form_source', 'learndash') // Défaut
            ->toHaveKey('form_ref', '') // Défaut
            ->toHaveKey('active', false) // Défaut
            ->toHaveKey('notes', ''); // Défaut
    });

    it('migrates legacy gf_form_id to new structure', function () {
        $this->setOption('wcqs_testpos_mapping', [
            '_version' => 1,
            'product_200' => [
                'page_id' => 300,
                'gf_form_id' => '42', // Ancienne structure
                'active' => true
            ]
        ]);
        WCQS_Mapping::clear_cache();

        $config = WCQS_Mapping::get_for_product(200);

        expect($config)
            ->toHaveKey('form_source', 'gravityforms')
            ->toHaveKey('form_ref', '42')
            ->not()->toHaveKey('gf_form_id'); // Supprimé après migration
    });

    it('checks if product has active test', function () {
        // Mock get_post pour simuler une page publiée
        if (!function_exists('get_post')) {
            function get_post($page_id) {
                if ($page_id == 456) {
                    return (object) ['post_status' => 'publish'];
                }
                if ($page_id == 457) {
                    return (object) ['post_status' => 'publish'];
                }
                return null;
            }
        }

        expect(WCQS_Mapping::has_active_test(123))->toBeTrue(); // Active + page existe
        expect(WCQS_Mapping::has_active_test(124))->toBeFalse(); // Inactif
        expect(WCQS_Mapping::has_active_test(125))->toBeFalse(); // Page manquante
        expect(WCQS_Mapping::has_active_test(999))->toBeFalse(); // Produit inexistant
    });

    it('gets test URL for product', function () {
        // Mock get_permalink
        if (!function_exists('get_permalink')) {
            function get_permalink($page_id) {
                return $page_id == 456 ? 'https://example.com/test-page' : false;
            }
        }

        if (!function_exists('add_query_arg')) {
            function add_query_arg($args, $url) {
                $query = http_build_query($args);
                return $url . '?' . $query;
            }
        }

        $url = WCQS_Mapping::get_test_url(123);
        expect($url)
            ->toBeString()
            ->toContain('https://example.com/test-page')
            ->toContain('product_id=123');

        // Avec paramètres personnalisés
        $url_custom = WCQS_Mapping::get_test_url(123, ['user_id' => 42, 'source' => 'cart']);
        expect($url_custom)
            ->toContain('user_id=42')
            ->toContain('source=cart');

        // Produit inactif
        $url_inactive = WCQS_Mapping::get_test_url(124);
        expect($url_inactive)->toBeNull();
    });

    it('gets all products with filtering', function () {
        $all_products = WCQS_Mapping::get_all_products();
        expect($all_products)
            ->toBeArray()
            ->toHaveCount(3)
            ->toHaveKey(123)
            ->toHaveKey(124)
            ->toHaveKey(125);

        $active_only = WCQS_Mapping::get_all_products(true);
        expect($active_only)
            ->toBeArray()
            ->toHaveCount(2) // 123 et 125 actifs
            ->toHaveKey(123)
            ->toHaveKey(125)
            ->not()->toHaveKey(124); // Inactif exclu
    });

    it('validates mapping configuration', function () {
        // Mock fonctions WordPress nécessaires
        if (!function_exists('wc_get_product')) {
            function wc_get_product($id) {
                return $id == 123 ? (object) ['get_status' => fn() => 'publish'] : null;
            }
        }

        if (!function_exists('get_post')) {
            function get_post($id) {
                return $id == 456 ? (object) ['post_status' => 'publish'] : null;
            }
        }

        // Configuration valide
        $valid_config = [
            'product_id' => 123,
            'page_id' => 456,
            'form_source' => 'learndash',
            'form_ref' => '789',
            'active' => true
        ];

        $result = WCQS_Mapping::validate_config($valid_config);
        expect($result['valid'])->toBeTrue();
        expect($result['errors'])->toBeEmpty();

        // Configuration invalide
        $invalid_config = [
            'product_id' => 0, // Invalid
            'page_id' => 0,    // Invalid
        ];

        $result = WCQS_Mapping::validate_config($invalid_config);
        expect($result['valid'])->toBeFalse();
        expect($result['errors'])->not()->toBeEmpty();
        expect($result['errors'])->toContain('Product ID is required');
        expect($result['errors'])->toContain('Test page ID is required');
    });

    it('provides mapping statistics', function () {
        $stats = WCQS_Mapping::get_stats();

        expect($stats)
            ->toBeArray()
            ->toHaveKey('total', 3)
            ->toHaveKey('active', 2)
            ->toHaveKey('inactive', 1)
            ->toHaveKey('problematic')
            ->toHaveKey('health_score');

        expect($stats['total'])->toBe(3);
        expect($stats['active'])->toBe(2);
        expect($stats['inactive'])->toBe(1);
        expect($stats['health_score'])->toBeFloat();
    });

    it('searches in mapping data', function () {
        // Mock wc_get_product pour la recherche
        if (!function_exists('wc_get_product')) {
            function wc_get_product($id) {
                $products = [
                    123 => (object) ['get_name' => fn() => 'Formation WordPress Avancée'],
                    124 => (object) ['get_name' => fn() => 'Formation PHP Débutant'],
                    125 => (object) ['get_name' => fn() => 'Formation React Native']
                ];
                return $products[$id] ?? null;
            }
        }

        if (!function_exists('get_post')) {
            function get_post($id) {
                $pages = [
                    456 => (object) ['post_title' => 'Test WordPress'],
                    457 => (object) ['post_title' => 'Test PHP']
                ];
                return $pages[$id] ?? null;
            }
        }

        $results = WCQS_Mapping::search('WordPress');
        expect($results)
            ->toBeArray()
            ->toHaveKey(123); // Formation WordPress trouvée

        $results_php = WCQS_Mapping::search('PHP');
        expect($results_php)->toHaveKey(124);

        $results_empty = WCQS_Mapping::search('Java');
        expect($results_empty)->toBeEmpty();

        $results_notes = WCQS_Mapping::search('Formation A');
        expect($results_notes)->toHaveKey(123); // Trouvé dans les notes
    });

    it('manages cache correctly', function () {
        // Premier accès - charge depuis DB
        $mapping1 = WCQS_Mapping::get_mapping();
        expect($mapping1)->toHaveKey('product_123');

        // Modifier l'option directement (simulation changement externe)
        $this->setOption('wcqs_testpos_mapping', ['_version' => 2, 'product_999' => []]);

        // Deuxième accès - encore depuis cache (pas de changement)
        $mapping2 = WCQS_Mapping::get_mapping();
        expect($mapping2)->toBe($mapping1); // Cache utilisé

        // Vider le cache
        WCQS_Mapping::clear_cache();

        // Troisième accès - recharge depuis DB
        $mapping3 = WCQS_Mapping::get_mapping();
        expect($mapping3)
            ->not()->toBe($mapping1) // Nouvelles données
            ->toHaveKey('_version', 2)
            ->toHaveKey('product_999');

        // Refresh cache (shortcut)
        $this->setOption('wcqs_testpos_mapping', ['_version' => 3]);
        $mapping4 = WCQS_Mapping::refresh_cache();
        expect($mapping4)->toHaveKey('_version', 3);
    });

    it('handles corrupted mapping data gracefully', function () {
        // Mapping corrompu (non-array)
        $this->setOption('wcqs_testpos_mapping', 'corrupted_string');
        WCQS_Mapping::clear_cache();

        $mapping = WCQS_Mapping::get_mapping();
        expect($mapping)
            ->toBeArray()
            ->toHaveKey('_version', 1); // Fallback appliqué

        // Configuration produit corrompue
        $this->setOption('wcqs_testpos_mapping', [
            '_version' => 1,
            'product_500' => 'not_an_array'
        ]);
        WCQS_Mapping::clear_cache();

        $config = WCQS_Mapping::get_for_product(500);
        expect($config)->toBeNull(); // Gracefully handled
    });

});
