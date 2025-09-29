# 🧪 Guide Tests IDE - WC Qualiopi Steps

## 🚀 Lancement des Tests

### Via VS Code (Bouton "Run")

1. **Ouvrir le fichier** `run_tests.py` ou `quick_test.py`
2. **Cliquer sur "Run"** en haut à droite
3. **Choisir la configuration** :
   - `🧪 Tests Unitaires Complets` : Tests + rapport HTML/JSON
   - `⚡ Tests Rapides` : Tests uniquement (plus rapide)
   - `📊 Tests + Rapports` : Tous les tests + rapports

### Via Terminal

```bash
# Tests rapides
python quick_test.py

# Tests avec rapports complets
python run_tests.py unit

# Tests via Composer
composer test:unit
```

### Via Palette de Commandes

1. **Ctrl+Shift+P** → "Tasks: Run Task"
2. Choisir :
   - `🧪 Tests Unitaires`
   - `📊 Tests + Rapport HTML`
   - `⚡ Tests Rapides`

## 📊 Rapports Générés

### Localisation
- **Dossier** : `test_reports/`
- **JSON** : `test_results_YYYYMMDD_HHMMSS.json`
- **HTML** : `test_report_YYYYMMDD_HHMMSS.html`
- **Dernier rapport** : `latest_report.html` (lien symbolique)

### Contenu des Rapports

#### Rapport JSON
```json
{
  "passed": 47,
  "failed": 1,
  "total": 48,
  "assertions": 188,
  "success_rate": 97.9,
  "duration": 20.99,
  "timestamp": "2025-09-29T15:30:45",
  "errors": [...],
  "output": "...",
  "test_details": [...]
}
```

#### Rapport HTML
- **Vue d'ensemble** : Statistiques visuelles
- **Graphiques** : Barre de progression, taux de réussite
- **Détails** : Tests échoués avec contexte
- **Sortie complète** : Log des tests
- **Design responsive** : Compatible mobile

## 🎯 Types de Tests

### Tests Unitaires (`tests/Unit/`)
- **CheckoutDecision** : Logique de décision checkout
- **Token** : Gestion tokens HMAC
- **Session** : Gestion sessions WooCommerce
- **Plugin** : Singleton et configuration

### Commandes Disponibles
```bash
# Tests unitaires uniquement
composer test:unit
python run_tests.py unit

# Tests d'intégration (si disponibles)
composer test:integration
python run_tests.py integration

# Tous les tests
composer test
python run_tests.py all
```

## 🔧 Configuration

### Scripts Disponibles

| Script | Usage | Rapports |
|--------|--------|----------|
| `run_tests.py` | Complet avec rapports | JSON + HTML |
| `quick_test.py` | Rapide sans rapport | Terminal uniquement |

### Arguments
```bash
# Types de tests
python run_tests.py unit        # Tests unitaires
python run_tests.py integration # Tests d'intégration
python run_tests.py all         # Tous les tests
```

## 📈 Interprétation des Résultats

### Codes de Sortie
- **0** : Tous les tests passés ✅
- **1** : Erreur ou tests échoués ❌

### Métriques Importantes
- **Taux de réussite** : % de tests passés
- **Assertions** : Nombre total de vérifications
- **Durée** : Temps d'exécution total

### Statuts des Tests
- **✅ PASSED** : Test réussi
- **❌ FAILED** : Test échoué
- **⏸️ SKIPPED** : Test ignoré

## 🚨 Dépannage

### Erreurs Communes

#### `composer: command not found`
```bash
# Installer Composer ou utiliser le chemin complet
./vendor/bin/pest tests/Unit/
```

#### `Python script not found`
```bash
# Vérifier que vous êtes dans le bon répertoire
cd Plugins/wc_qualiopi_steps/
python run_tests.py
```

#### Tests bloqués
```bash
# Forcer l'arrêt et relancer
Ctrl+C
python quick_test.py
```

### Logs de Debug
- **Bootstrap** : `Bootstrap WC Qualiopi Steps tests loaded.`
- **Erreurs PHP** : Vérifier la sortie stderr
- **Timeouts** : Réduire la complexité des tests

## 📝 Maintenance

### Ajout de Nouveaux Tests
1. **Créer le fichier** dans `tests/Unit/`
2. **Suivre la convention** `*Test.php`
3. **Utiliser Pest** : `it('should do something', function() { ... });`
4. **Lancer les tests** pour validation

### Mise à Jour des Rapports
- Les rapports sont **générés automatiquement**
- **Horodatage** dans le nom de fichier
- **Lien symbolique** `latest_report.html` toujours à jour

---

**Dernière mise à jour** : 2025-09-29  
**Version** : 1.0  
**Compatibilité** : VS Code, Terminal, CI/CD
