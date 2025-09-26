<?php
/**
 * Script de réinitialisation COMPLETE des validations
 * wp eval-file reset_validation_complete.php --path=/sites/tb-formation.fr/files
 */

if (!defined('ABSPATH')) {
    die('Direct access not allowed');
}

echo "=== RÉINITIALISATION COMPLÈTE DES VALIDATIONS ===\n";

$user_id = get_current_user_id() ?: 5;
$product_id = 4017;

echo "User ID: {$user_id}\n";
echo "Product ID: {$product_id}\n\n";

// 1. Nettoyer TOUTES les sessions WooCommerce
if (class_exists('\\WcQualiopiSteps\\Utils\\WCQS_Session')) {
    \WcQualiopiSteps\Utils\WCQS_Session::force_clear_product($product_id);
    echo "✅ Sessions WooCommerce nettoyées complètement\n";
}

// 2. Supprimer TOUTES les user meta possibles
$meta_patterns = [
    "_wcqs_testpos_ok_{$product_id}",
    "_wcqs_testpos_validated_{$product_id}",
    "_wcqs_test_{$product_id}",
    "_qualiopi_test_{$product_id}"
];

foreach ($meta_patterns as $meta_key) {
    delete_user_meta($user_id, $meta_key);
    echo "✅ User meta supprimée: {$meta_key}\n";
}

// 3. Nettoyer aussi pour tous les utilisateurs (au cas où)
global $wpdb;
$wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
    '%wcqs%' . $product_id . '%',
    '%testpos%' . $product_id . '%'
));
echo "✅ Toutes les meta utilisateur nettoyées globalement\n";

// 4. Forcer le vidage du cache Cart_Guard
if (class_exists('\\WcQualiopiSteps\\Frontend\\Cart_Guard')) {
    $cart_guard = \WcQualiopiSteps\Frontend\Cart_Guard::get_instance();
    $cart_guard->clear_cache();
    echo "✅ Cache Cart_Guard vidé\n";
}

// 5. Vider les transients WordPress
delete_transient('wcqs_validation_' . $product_id);
delete_transient('wcqs_test_' . $product_id);
echo "✅ Transients WordPress nettoyés\n";

echo "\n=== RÉINITIALISATION TERMINÉE ===\n";
echo "Videz maintenant le cache de votre navigateur et rechargez la page.\n";
