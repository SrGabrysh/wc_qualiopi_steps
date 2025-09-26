# 📊 Documentation du Système de Logs - WC Qualiopi Steps

## 📍 Vue d'ensemble

Le plugin WC Qualiopi Steps utilise un système de logging centralisé via la classe `WCQS_Logger`. Tous les logs sont stockés dans des fichiers quotidiens avec rotation automatique.

## 🗂️ Emplacement des Logs

- **Dossier principal** : `/wp-content/uploads/wcqs-logs/`
- **Format des fichiers** : `wcqs-YYYY-MM-DD.log`
- **Exemple** : `wcqs-2025-09-26.log`
- **Protection** : Dossier protégé par `.htaccess` (Deny from all)

## 📝 Format des Logs

### Structure Standard
```
[TIMESTAMP] LEVEL   [USER:ID] [URI] MESSAGE {CONTEXT}
```

### Exemple Réel
```
[2025-09-26T15:57:45+02:00] INFO    [USER:1] [/wp-admin/] Plugin initialization complete
[2025-09-26T15:58:12+02:00] DEBUG   [USER:0] [/panier/] Cart_Guard: Checking cart items {"items_count":2}
[2025-09-26T15:58:15+02:00] WARNING [USER:0] [/commander/] Cart_Guard: Blocking checkout {"pending_tests":1}
[2025-09-26T15:58:20+02:00] ERROR   [USER:1] [/wp-admin/] Database connection failed {"error":"Connection timeout"}
```

## 🏷️ Niveaux de Log

| Niveau   | Usage                                    | Couleur Console |
|----------|------------------------------------------|-----------------|
| DEBUG    | Informations détaillées de débogage     | Gris            |
| INFO     | Informations générales                   | Bleu            |
| WARNING  | Avertissements non critiques             | Orange          |
| ERROR    | Erreurs récupérables                     | Rouge           |
| CRITICAL | Erreurs fatales                          | Rouge foncé     |

## 🔧 Configuration

### Changer le Niveau de Log
```php
// Via code PHP
$logger = \WcQualiopiSteps\Utils\WCQS_Logger::get_instance();
$logger->set_log_level('INFO'); // DEBUG, INFO, WARNING, ERROR, CRITICAL

// Via option WordPress
update_option('wcqs_log_level', 'DEBUG');
```

### Statistiques du Système
```php
$logger = \WcQualiopiSteps\Utils\WCQS_Logger::get_instance();
$stats = $logger->get_stats();
/*
Array (
    'exists' => true,
    'file_path' => '/path/to/wcqs-2025-09-26.log',
    'size' => 15420,
    'size_human' => '15.06 KB',
    'lines' => 342,
    'writable' => true,
    'last_modified' => 1727363865,
    'last_modified_human' => '5 minutes ago',
    'log_level' => 'DEBUG'
)
*/
```

## 🔍 Consultation des Logs

### 1. Interface Admin WordPress
- Aller dans **WC Qualiopi Steps → Console de Logs**
- Filtres disponibles : période, niveau, source
- Actualisation temps réel possible
- Export JSON intégré

### 2. Scripts SSH Python
```bash
# Logs généraux
python Scripts/quick_logs.py

# Logs Cart_Guard uniquement
python Scripts/quick_logs.py cart_guard

# Erreurs uniquement
python Scripts/quick_logs.py error

# Suivi temps réel
python Scripts/quick_logs.py live
```

### 3. Ligne de Commande Serveur
```bash
# Dernières 50 lignes
tail -50 /wp-content/uploads/wcqs-logs/wcqs-$(date +%Y-%m-%d).log

# Suivre en temps réel
tail -f /wp-content/uploads/wcqs-logs/wcqs-$(date +%Y-%m-%d).log

# Rechercher les erreurs
grep -E "(ERROR|CRITICAL)" /wp-content/uploads/wcqs-logs/wcqs-*.log

# Compter les logs par niveau
grep -o '\] [A-Z]*' /wp-content/uploads/wcqs-logs/wcqs-*.log | sort | uniq -c
```

## 🛠️ Requêtes Utiles

### Grep/Awk Patterns

```bash
# Extraire tous les messages Cart_Guard
grep "Cart_Guard" wcqs-*.log

# Logs d'un utilisateur spécifique
grep "\[USER:123\]" wcqs-*.log

# Erreurs PHP fatales
grep "PHP Fatal Error" wcqs-*.log

# Activité sur une URI spécifique
grep "\[/panier/\]" wcqs-*.log

# Extraire les contextes JSON
grep -o '{.*}$' wcqs-*.log | jq '.'

# Statistiques par niveau (awk)
awk '/^\[.*\] [A-Z]+/ { match($0, /\] ([A-Z]+)/, arr); print arr[1] }' wcqs-*.log | sort | uniq -c
```

### Analyse Temporelle
```bash
# Activité par heure
awk '/^\[.*T([0-9]{2})/ { match($0, /T([0-9]{2})/, arr); print arr[1] }' wcqs-*.log | sort | uniq -c

# Logs des 5 dernières minutes
awk -v cutoff=$(date -d '5 minutes ago' +%s) '
/^\[([^]]+)\]/ { 
    match($0, /\[([^]]+)\]/, arr); 
    gsub(/[TZ]/, " ", arr[1]); 
    if (mktime(gensub(/[-:]/, " ", "g", arr[1])) >= cutoff) print 
}' wcqs-*.log
```

## 🔄 Rotation des Logs

### Automatique
- **Seuil** : 10 MB par fichier
- **Action** : Compression gzip automatique
- **Format archivé** : `wcqs-YYYY-MM-DD.log.TIMESTAMP.gz`
- **Nettoyage** : Fichier principal vidé après archivage

### Manuel
```bash
# Archiver manuellement
gzip wcqs-2025-09-25.log

# Vider le fichier actuel
echo "" > wcqs-$(date +%Y-%m-%d).log

# Supprimer les anciens (> 30 jours)
find /wp-content/uploads/wcqs-logs/ -name "wcqs-*.log.*.gz" -mtime +30 -delete
```

## 🚨 Dépannage

### Problèmes Courants

**1. Logs non générés**
```bash
# Vérifier les permissions
ls -la /wp-content/uploads/wcqs-logs/

# Tester l'écriture
touch /wp-content/uploads/wcqs-logs/test.log
```

**2. Fichier trop volumineux**
```php
// Forcer la rotation
$logger = \WcQualiopiSteps\Utils\WCQS_Logger::get_instance();
$logger->clear_logs(); // Vide le fichier actuel
```

**3. Logs en double**
- Vérifier qu'aucun autre système de logging n'interfère
- S'assurer qu'une seule instance du plugin est active

### Mode Debug Avancé
```php
// Activer le debug maximum
define('WCQS_DEBUG_LOGS', true);
add_filter('wcqs_log_level', function() { return 'DEBUG'; });

// Logger toutes les actions WordPress
add_action('all', function($hook) {
    if (strpos($hook, 'wcqs') !== false || strpos($hook, 'woocommerce') !== false) {
        wcqs_log('DEBUG', "Hook fired: $hook");
    }
});
```

## 📊 Métriques et Monitoring

### Indicateurs Clés
- **Volume** : Nombre de logs par heure/jour
- **Erreurs** : Ratio ERROR+CRITICAL vs total
- **Performance** : Temps de réponse des hooks
- **Utilisation** : Pages les plus loggées

### Dashboard Simple
```bash
#!/bin/bash
# Script de monitoring simple
LOG_FILE="/wp-content/uploads/wcqs-logs/wcqs-$(date +%Y-%m-%d).log"

echo "=== WCQS Logs Dashboard ==="
echo "Date: $(date)"
echo "File: $LOG_FILE"
echo ""

if [ -f "$LOG_FILE" ]; then
    echo "Total lines: $(wc -l < $LOG_FILE)"
    echo "File size: $(du -h $LOG_FILE | cut -f1)"
    echo ""
    
    echo "Levels breakdown:"
    grep -o '\] [A-Z]*' "$LOG_FILE" | sort | uniq -c | sort -nr
    echo ""
    
    echo "Last 5 entries:"
    tail -5 "$LOG_FILE"
else
    echo "No log file found for today"
fi
```

## 🔗 Intégration avec Outils Externes

### Logrotate (Linux)
```bash
# /etc/logrotate.d/wcqs
/wp-content/uploads/wcqs-logs/wcqs-*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    copytruncate
}
```

### Monitoring avec Telegraf
```toml
[[inputs.tail]]
  files = ["/wp-content/uploads/wcqs-logs/wcqs-*.log"]
  from_beginning = false
  pipe = false
  
  data_format = "grok"
  grok_patterns = [
    "\\[%{TIMESTAMP_ISO8601:timestamp}\\] %{WORD:level} \\[USER:%{NUMBER:user_id}\\] \\[%{DATA:uri}\\] %{GREEDYDATA:message}"
  ]
```

---

**Version de la documentation** : 0.7.0  
**Dernière mise à jour** : 2025-09-26  
**Auteur** : TB-Web
