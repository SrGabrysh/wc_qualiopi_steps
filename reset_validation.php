<?php
/**
 * Script de réinitialisation des validations pour tests
 * À exécuter via WP-CLI : wp eval-file reset_validation.php --path=/sites/tb-formation.fr/files
 */

if (!defined('ABSPATH')) {
    die('Direct access not allowed');
}

echo "=== RÉINITIALISATION DES VALIDATIONS ===\n";

$user_id = 5; // Votre user ID admin
$product_id = 4017;

echo "User ID: {$user_id}\n";
echo "Product ID: {$product_id}\n\n";

// 1. Vider la session WooCommerce
if (function_exists('WC') && WC() && WC()->session) {
    // Supprimer toutes les clés de session liées au plugin
    $session_keys_to_remove = [];
    foreach (WC()->session->get_session_data() as $key => $value) {
        if (strpos($key, 'wcqs_') === 0 || strpos($key, 'testpos_') === 0) {
            $session_keys_to_remove[] = $key;
        }
    }
    
    foreach ($session_keys_to_remove as $key) {
        WC()->session->__unset($key);
        echo "✅ Session key removed: {$key}\n";
    }
    
    // Spécifiquement supprimer la clé du produit
    $session_key = "wcqs_testpos_solved_{$product_id}";
    WC()->session->__unset($session_key);
    echo "✅ Session validation cleared for product {$product_id}\n";
    
} else {
    echo "⚠️ WooCommerce session not available\n";
}

// 2. Supprimer les user meta
$meta_key = "_wcqs_testpos_ok_{$product_id}";
$deleted = delete_user_meta($user_id, $meta_key);
echo "✅ User meta cleared: {$meta_key} " . ($deleted ? "(DELETED)" : "(NOT_FOUND)") . "\n";

// 3. Vider tous les user meta liés au plugin
$all_meta = get_user_meta($user_id);
$deleted_count = 0;
foreach ($all_meta as $key => $value) {
    if (strpos($key, '_wcqs_') === 0 || strpos($key, '_testpos_') === 0) {
        delete_user_meta($user_id, $key);
        echo "✅ User meta cleared: {$key}\n";
        $deleted_count++;
    }
}

echo "\n=== RÉSUMÉ ===\n";
echo "✅ Session WooCommerce vidée\n";
echo "✅ {$deleted_count} user meta supprimées\n";
echo "✅ Validation réinitialisée pour le produit {$product_id}\n";
echo "\n🔄 Rafraîchissez maintenant votre page panier pour tester !\n";
