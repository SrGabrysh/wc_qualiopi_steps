<?php
/**
 * Script de debug avec simulation utilisateur admin et panier
 * À exécuter via WP-CLI avec --user=admin
 */

if (!defined('ABSPATH')) {
    die('Direct access not allowed');
}

echo "=== DEBUG CART_GUARD AVEC UTILISATEUR ADMIN ET PANIER ===\n";

// 1. Forcer un utilisateur admin
$admin_users = get_users(['role' => 'administrator', 'number' => 1]);
if (empty($admin_users)) {
    echo "❌ Aucun utilisateur admin trouvé !\n";
    exit;
}

$admin_user = $admin_users[0];
wp_set_current_user($admin_user->ID);

echo "✅ Utilisateur admin forcé :\n";
echo "   - ID: {$admin_user->ID}\n";
echo "   - Login: {$admin_user->user_login}\n";
echo "   - Email: {$admin_user->user_email}\n";

// 2. Trouver un produit avec mapping actif
if (!class_exists('\\WcQualiopiSteps\\Utils\\WCQS_Mapping')) {
    echo "❌ Classe WCQS_Mapping non disponible\n";
    exit;
}

$mapping = \WcQualiopiSteps\Utils\WCQS_Mapping::get_mapping();
$test_product_id = null;

foreach ($mapping as $key => $config) {
    if (strpos($key, 'product_') === 0 && is_array($config) && !empty($config['active'])) {
        $test_product_id = (int) str_replace('product_', '', $key);
        break;
    }
}

if (!$test_product_id) {
    echo "❌ Aucun produit avec mapping actif trouvé !\n";
    echo "Mapping disponible: " . json_encode($mapping, JSON_PRETTY_PRINT) . "\n";
    exit;
}

echo "✅ Produit de test trouvé: {$test_product_id}\n";

// 3. Ajouter le produit au panier
if (!function_exists('WC') || !WC() || !WC()->cart) {
    echo "❌ WooCommerce ou panier non disponible\n";
    exit;
}

// Vider le panier d'abord
WC()->cart->empty_cart();

// Ajouter le produit
$added = WC()->cart->add_to_cart($test_product_id, 1);
if (!$added) {
    echo "❌ Impossible d'ajouter le produit au panier\n";
    exit;
}

echo "✅ Produit ajouté au panier\n";
echo "   - Produit ID: {$test_product_id}\n";
echo "   - Items dans le panier: " . WC()->cart->get_cart_contents_count() . "\n";

// 4. Tester l'état actuel (avant validation)
echo "\n=== ÉTAT AVANT VALIDATION ===\n";

if (class_exists('\\WcQualiopiSteps\\Frontend\\Cart_Guard')) {
    $cart_guard = \WcQualiopiSteps\Frontend\Cart_Guard::get_instance();
    $reflection = new ReflectionClass($cart_guard);
    
    // Test should_block_checkout
    $should_block_method = $reflection->getMethod('should_block_checkout');
    $should_block_method->setAccessible(true);
    $should_block = $should_block_method->invoke($cart_guard);
    echo "should_block_checkout(): " . ($should_block ? 'TRUE' : 'FALSE') . "\n";
    
    // Test is_test_validated
    $is_test_validated_method = $reflection->getMethod('is_test_validated');
    $is_test_validated_method->setAccessible(true);
    $is_validated = $is_test_validated_method->invoke($cart_guard, $admin_user->ID, $test_product_id);
    echo "is_test_validated({$admin_user->ID}, {$test_product_id}): " . ($is_validated ? 'TRUE' : 'FALSE') . "\n";
}

// 5. Simuler la validation comme le ferait l'AJAX
echo "\n=== SIMULATION VALIDATION ===\n";

// Session WooCommerce
if (class_exists('\\WcQualiopiSteps\\Utils\\WCQS_Session')) {
    $session_set = \WcQualiopiSteps\Utils\WCQS_Session::set_solved($test_product_id, 3600);
    echo "Session set_solved: " . ($session_set ? 'SUCCESS' : 'FAILED') . "\n";
    
    $session_check = \WcQualiopiSteps\Utils\WCQS_Session::is_solved($test_product_id);
    echo "Session is_solved: " . ($session_check ? 'SOLVED' : 'NOT_SOLVED') . "\n";
}

// User meta
$meta_key = "_wcqs_testpos_ok_{$test_product_id}";
$timestamp = date('c');
$meta_updated = update_user_meta($admin_user->ID, $meta_key, $timestamp);
echo "User meta update: " . ($meta_updated ? 'SUCCESS' : 'FAILED') . "\n";

$meta_check = get_user_meta($admin_user->ID, $meta_key, true);
echo "User meta verification: " . ($meta_check ? $meta_check : 'EMPTY') . "\n";

// 6. Tester l'état après validation
echo "\n=== ÉTAT APRÈS VALIDATION ===\n";

if (class_exists('\\WcQualiopiSteps\\Frontend\\Cart_Guard')) {
    // Vider le cache
    $cart_guard->clear_cache();
    echo "Cache Cart_Guard vidé\n";
    
    // Re-tester
    $should_block = $should_block_method->invoke($cart_guard);
    echo "should_block_checkout(): " . ($should_block ? 'TRUE' : 'FALSE') . "\n";
    
    $is_validated = $is_test_validated_method->invoke($cart_guard, $admin_user->ID, $test_product_id);
    echo "is_test_validated({$admin_user->ID}, {$test_product_id}): " . ($is_validated ? 'TRUE' : 'FALSE') . "\n";
    
    // Test get_pending_tests_info
    $pending_tests_method = $reflection->getMethod('get_pending_tests_info');
    $pending_tests_method->setAccessible(true);
    $pending_tests = $pending_tests_method->invoke($cart_guard);
    echo "Pending tests count: " . count($pending_tests) . "\n";
    
    if (!empty($pending_tests)) {
        echo "Pending tests:\n";
        foreach ($pending_tests as $test) {
            echo "  - Product {$test['product_id']}: {$test['product_name']}\n";
        }
    }
}

echo "\n=== CONCLUSION ===\n";
if (isset($should_block) && !$should_block) {
    echo "✅ SUCCÈS : Le blocage est levé après validation !\n";
} else {
    echo "❌ ÉCHEC : Le blocage persiste malgré la validation\n";
}

echo "\n=== DEBUG TERMINÉ ===\n";
