<?php
/**
 * Script de debug temporaire pour Cart_Guard
 * À placer dans le répertoire racine du plugin et exécuter via WP-CLI
 * 
 * Usage: wp eval-file debug_cart_guard.php
 */

if (!defined('ABSPATH')) {
    die('Direct access not allowed');
}

echo "=== DEBUG CART_GUARD POUR ADMIN CONNECTÉ ===\n";

// 1. Vérifier l'état de l'utilisateur
$current_user_id = get_current_user_id();
echo "User ID: {$current_user_id}\n";

if ($current_user_id > 0) {
    $user = get_userdata($current_user_id);
    echo "User login: {$user->user_login}\n";
    echo "User roles: " . implode(', ', $user->roles) . "\n";
    echo "Is admin: " . (current_user_can('administrator') ? 'YES' : 'NO') . "\n";
}

// 2. Vérifier WooCommerce et panier
echo "\n=== WOOCOMMERCE STATE ===\n";
echo "WC() available: " . (function_exists('WC') ? 'YES' : 'NO') . "\n";

if (function_exists('WC') && WC()) {
    echo "WC()->cart available: " . (WC()->cart ? 'YES' : 'NO') . "\n";
    
    if (WC()->cart) {
        $cart_items = WC()->cart->get_cart();
        echo "Cart items count: " . count($cart_items) . "\n";
        
        foreach ($cart_items as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];
            echo "  - Product ID: {$product_id}\n";
            
            // Vérifier mapping
            if (class_exists('\\WcQualiopiSteps\\Utils\\WCQS_Mapping')) {
                $mapping = \WcQualiopiSteps\Utils\WCQS_Mapping::get_for_product($product_id);
                echo "    Mapping found: " . ($mapping ? 'YES' : 'NO') . "\n";
                if ($mapping) {
                    echo "    Mapping active: " . (!empty($mapping['active']) ? 'YES' : 'NO') . "\n";
                    echo "    Page ID: " . ($mapping['page_id'] ?? 'N/A') . "\n";
                }
            }
        }
    }
    
    echo "WC()->session available: " . (WC()->session ? 'YES' : 'NO') . "\n";
    if (WC()->session) {
        echo "WC()->session class: " . get_class(WC()->session) . "\n";
    }
}

// 3. Vérifier les flags du plugin
echo "\n=== PLUGIN FLAGS ===\n";
if (class_exists('\\WcQualiopiSteps\\Core\\Plugin')) {
    $flags = \WcQualiopiSteps\Core\Plugin::get_flags();
    echo "Flags: " . json_encode($flags, JSON_PRETTY_PRINT) . "\n";
    echo "enforce_cart: " . ($flags['enforce_cart'] ? 'TRUE' : 'FALSE') . "\n";
}

// 4. Tester les sessions pour chaque produit du panier
echo "\n=== SESSION TESTS ===\n";
if (function_exists('WC') && WC() && WC()->cart) {
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        $product_id = $cart_item['product_id'];
        echo "Testing product {$product_id}:\n";
        
        if (class_exists('\\WcQualiopiSteps\\Utils\\WCQS_Session')) {
            $is_solved = \WcQualiopiSteps\Utils\WCQS_Session::is_solved($product_id);
            echo "  Session solved: " . ($is_solved ? 'YES' : 'NO') . "\n";
            
            $details = \WcQualiopiSteps\Utils\WCQS_Session::get_session_details($product_id);
            if ($details) {
                echo "  Session details: " . json_encode($details, JSON_PRETTY_PRINT) . "\n";
            } else {
                echo "  Session details: NONE\n";
            }
        }
        
        // Vérifier user meta
        if ($current_user_id > 0) {
            $meta_key = "_wcqs_testpos_ok_{$product_id}";
            $meta_value = get_user_meta($current_user_id, $meta_key, true);
            echo "  User meta ({$meta_key}): " . ($meta_value ? $meta_value : 'EMPTY') . "\n";
            
            if ($meta_value) {
                $timestamp = strtotime($meta_value);
                if ($timestamp) {
                    $age = time() - $timestamp;
                    echo "  Meta age: {$age} seconds\n";
                    echo "  Meta expired: " . ($age >= DAY_IN_SECONDS ? 'YES' : 'NO') . "\n";
                }
            }
        }
    }
}

// 5. Tester Cart_Guard directement
echo "\n=== CART_GUARD TESTS ===\n";
if (class_exists('\\WcQualiopiSteps\\Frontend\\Cart_Guard')) {
    // Utiliser réflexion pour accéder aux méthodes privées
    $cart_guard = \WcQualiopiSteps\Frontend\Cart_Guard::get_instance();
    $reflection = new ReflectionClass($cart_guard);
    
    // Test should_block_checkout
    $should_block_method = $reflection->getMethod('should_block_checkout');
    $should_block_method->setAccessible(true);
    $should_block = $should_block_method->invoke($cart_guard);
    echo "should_block_checkout(): " . ($should_block ? 'TRUE' : 'FALSE') . "\n";
    
    // Test get_pending_tests_info
    $pending_tests_method = $reflection->getMethod('get_pending_tests_info');
    $pending_tests_method->setAccessible(true);
    $pending_tests = $pending_tests_method->invoke($cart_guard);
    echo "Pending tests count: " . count($pending_tests) . "\n";
    
    if (!empty($pending_tests)) {
        echo "Pending tests details:\n";
        foreach ($pending_tests as $test) {
            echo "  - Product {$test['product_id']}: {$test['product_name']}\n";
        }
    }
    
    // Test is_test_validated pour chaque produit
    if (function_exists('WC') && WC() && WC()->cart) {
        $is_test_validated_method = $reflection->getMethod('is_test_validated');
        $is_test_validated_method->setAccessible(true);
        
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            $is_validated = $is_test_validated_method->invoke($cart_guard, $current_user_id, $product_id);
            echo "is_test_validated({$current_user_id}, {$product_id}): " . ($is_validated ? 'TRUE' : 'FALSE') . "\n";
        }
    }
}

echo "\n=== DEBUG TERMINÉ ===\n";
