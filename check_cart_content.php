<?php
// Script temporaire pour vérifier le contenu du panier
if (!defined('ABSPATH')) {
    die('Direct access not allowed');
}

echo "=== VÉRIFICATION CONTENU PANIER ===\n";

if (function_exists('WC') && WC() && WC()->cart) {
    $cart_items = WC()->cart->get_cart();
    echo "Items dans le panier: " . count($cart_items) . "\n";
    
    foreach ($cart_items as $key => $item) {
        echo "- Produit ID: " . $item['product_id'] . "\n";
        echo "  Nom: " . $item['data']->get_name() . "\n";
        echo "  Key: " . $key . "\n";
        echo "  Variation ID: " . ($item['variation_id'] ?? 'N/A') . "\n";
        
        // Vérifier le mapping pour ce produit
        if (class_exists('\\WcQualiopiSteps\\Utils\\WCQS_Mapping')) {
            $mapping = \WcQualiopiSteps\Utils\WCQS_Mapping::get_for_product($item['product_id']);
            echo "  Mapping trouvé: " . ($mapping ? 'OUI' : 'NON') . "\n";
            if ($mapping) {
                echo "  Mapping actif: " . (!empty($mapping['active']) ? 'OUI' : 'NON') . "\n";
                echo "  Page ID: " . ($mapping['page_id'] ?? 'N/A') . "\n";
            }
        }
        echo "\n";
    }
} else {
    echo "WooCommerce non disponible\n";
}
