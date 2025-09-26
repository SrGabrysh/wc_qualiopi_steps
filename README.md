# WC Qualiopi Steps

**Version:** 0.6.14  
**Auteur:** TB-Web

## Description

WC Qualiopi Steps est un plugin WooCommerce qui impose un test de positionnement (Qualiopi) avant paiement. Mapping produit→page de test via page d'options, jeton HMAC + session, garde checkout, logs d'audit et page fallback. Développement step-by-step, SRP, UX accessible.

**Note importante** : Ce plugin prend en charge les URLs françaises utilisées sur le site :

- Panier : `/panier/` (au lieu de `/cart/`)
- Checkout : `/commander/` (au lieu de `/checkout/`)

## Installation

1. Téléchargez le plugin
2. Activez-le dans l'administration WordPress

## Changelog

### v0.6.0 (2025-09-25)

- **Étape 3 terminée** : UX panier avec garde checkout
- **Nouveautés** :
  - Classe `Cart_Guard` : Masquage bouton Commander si test non validé
  - CTA "Passer le test" avec redirection automatique vers page de test
  - Messages accessibles avec `role="alert"` et `aria-live`
  - CSS responsive avec animations et support thème sombre
  - Intégration hooks WooCommerce pour UX fluide
  - Gestion fallback si page de test indisponible
  - Cache validation utilisateur pour optimiser performances
  - Support feature flag `enforce_cart` pour activation/désactivation

### v0.5.0 (2025-09-25)

- **Étape 2 terminée** : Utilitaires & sécurité
- **Nouveautés** :
  - Classe `WCQS_Token` : Gestion jetons HMAC avec TTL 2h et rotation de clé
  - Classe `WCQS_Session` : Gestion sessions WooCommerce avec TTL 30min
  - Classe `WCQS_Mapping` : Helpers optimisés avec cache statique
  - Tests unitaires PHPUnit complets pour Token et Session
  - Architecture modulaire Security/ et Utils/
  - Support rotation de clé avec acceptance temporaire N-1
  - Intégration complète dans Plugin.php avec vérifications

### v0.4.1 (2025-09-25)

- Étape 1 : Mapping central avec interface admin avancée
- Validation instantanée AJAX, Import/Export CSV, Contrôle live

---

Dernière mise à jour : 2025-09-25
