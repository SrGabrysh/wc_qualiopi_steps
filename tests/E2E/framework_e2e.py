#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Framework E2E pour WC Qualiopi Steps
Basé sur le modèle PTI_001_2.py - Version simplifiée pour tests unitaires E2E

Ce framework remplace les tests d'intégration complexes par des scripts
E2E interactifs qui testent le workflow réel utilisateur.
"""

import sys
import os
import json
import re
from datetime import datetime
from abc import ABC, abstractmethod

# Chemin vers le connecteur SSH
sys.path.append(os.path.join(os.path.dirname(__file__), '..', '..', '..', '..', 'Access'))

try:
    from ssh_access import TBWebSSHConnector
except ImportError as e:
    print(f"❌ Erreur d'import SSH: {e}")
    print("Framework E2E nécessite Access/ssh_access.py")

class E2ETestFramework(ABC):
    """
    Classe de base pour tous les tests E2E
    Fournit les outils communs : SSH, WP-CLI, observations utilisateur, rapports
    """
    
    def __init__(self, test_name):
        self.test_name = test_name
        self.ssh = None
        self.config = None
        self.start_time = datetime.now()
        self.phases_completed = []
        self.report_data = {
            'meta': {'test_name': test_name},
            'phases': [],
            'javascript_results': [],
            'backend_verifications': [],
            'user_observations': []
        }
        
    def log(self, message, level="INFO"):
        """Logger unifié avec icônes"""
        timestamp = datetime.now().strftime('%H:%M:%S')
        levels = {
            'INFO': '📝', 'SUCCESS': '✅', 'ERROR': '❌', 'WARNING': '⚠️',
            'ACTION': '🎯', 'JAVASCRIPT': '📜', 'VALIDATION': '🔍'
        }
        icon = levels.get(level, '📝')
        print(f"[{timestamp}] {icon} {message}")

    def load_config(self):
        """Chargement configuration SSH standardisé"""
        config_paths = [
            os.path.normpath(os.path.join(
                os.path.dirname(__file__), '..', '..', '..', '..', 'Access', 'ssh_wpcli_access_config.json'
            )),
        ]

        for config_path in config_paths:
            try:
                if os.path.exists(config_path):
                    with open(config_path, 'r', encoding='utf-8') as f:
                        self.config = json.load(f)
                        self.log(f"Configuration chargée: {config_path}")
                        return True
            except Exception as e:
                self.log(f"Erreur config {config_path}: {e}", "ERROR")
                continue

        self.log("Aucun fichier de configuration valide trouvé", "ERROR")
        return False

    def connect_ssh(self):
        """Connexion SSH standardisée"""
        try:
            self.log("Connexion SSH au serveur TB-Web...")
            self.ssh = TBWebSSHConnector()
            
            if hasattr(self.ssh, 'connect_ssh') and callable(getattr(self.ssh, 'connect_ssh')):
                if not self.ssh.connect_ssh():
                    raise Exception("Échec de connexion SSH")
            
            self.log("Connexion SSH établie", "SUCCESS")
            return True
        except Exception as e:
            self.log(f"Erreur de connexion SSH: {e}", "ERROR")
            return False

    def disconnect_ssh(self):
        """Déconnexion SSH propre"""
        try:
            if self.ssh and hasattr(self.ssh, 'disconnect'):
                self.ssh.disconnect()
                self.log("Déconnexion SSH", "SUCCESS")
        except Exception as e:
            self.log(f"Erreur déconnexion SSH: {e}", "WARNING")

    def execute_wp_command(self, command):
        """Commandes WP-CLI avec path automatique"""
        try:
            wp_path = self.config.get('wordpress', {}).get('wp_path', '/sites/tb-formation.fr/files')
            
            if not command.startswith('wp '):
                command = f'wp {command}'
            if '--path=' not in command:
                command = f'{command} --path={wp_path}'
            
            output = self.ssh.execute_command(command)
            return output
        except Exception as e:
            self.log(f"Erreur exécution WP-CLI: {e}", "ERROR")
            return None

    def wait_for_user_action(self, phase_name, instructions, has_validation=True):
        """Interface utilisateur standardisée pour les phases"""
        print("\n" + "="*80)
        self.log(f"PHASE : {phase_name}", "ACTION")
        print("="*80)
        
        print("\n📋 INSTRUCTIONS À SUIVRE :")
        for i, instruction in enumerate(instructions, 1):
            print(f"   {i}. {instruction}")
        
        if has_validation:
            print("\n🔍 Une fois terminé, ce script vérifiera automatiquement l'état backend.")
        
        while True:
            print("\n" + "-"*60)
            response = input("Appuyez sur 'O' une fois les actions terminées, 'S' pour ignorer, 'Q' pour quitter: ").upper()
            if response == 'O':
                return True
            elif response == 'S':
                self.log(f"Phase {phase_name} ignorée", "WARNING")
                return False
            elif response == 'Q':
                self.log("Arrêt du script demandé par l'utilisateur", "INFO")
                sys.exit(0)
            else:
                print("❌ Réponse invalide. Utilisez 'O', 'S' ou 'Q'")

    def collect_user_observations(self, context, questions):
        """Collecte structurée des observations utilisateur"""
        print("\n" + "="*80)
        print(f"🔍 COLLECTE DE VOS OBSERVATIONS - {context.upper()}")
        print("="*80)
        print("💡 IMPORTANT : Répondez selon ce que VOUS avez vu et ressenti")
        print("   (pas de connaissances techniques requises)")
        
        observations = {}
        for key, question in questions:
            print(f"\n❓ {question}")
            
            # Aide contextuelle pour certaines questions
            if "oui/non" in question.lower():
                print("   💬 Répondez simplement 'oui' ou 'non'")
            elif "décrivez" in question.lower():
                print("   💬 Décrivez en quelques mots ce que vous avez observé")
            
            response = input(">>> Votre réponse : ").strip()
            observations[key] = response
            if response:
                self.log(f"Observation {context} {key}: {response}")
        
        # Sauvegarder dans le rapport
        observation_data = {
            'phase': f"{context} - Observations Utilisateur",
            'timestamp': datetime.now().isoformat(),
            'observations': observations
        }
        
        self.report_data['user_observations'].append(observation_data)
        self.log(f"Observations {context} collectées avec succès", "SUCCESS")
        return observations

    def display_javascript_snippet(self, snippet_name, javascript_code, collect_results=True):
        """Afficher snippet JavaScript et collecter les résultats"""
        print("\n" + "="*80)
        self.log(f"JAVASCRIPT CONSOLE : {snippet_name}", "JAVASCRIPT")
        print("="*80)
        
        print("\n📜 COPIEZ-COLLEZ ce code dans Chrome DevTools Console :")
        print("-"*60)
        print(javascript_code)
        print("-"*60)
        
        snippet_data = {
            'name': snippet_name,
            'code': javascript_code,
            'timestamp': datetime.now().isoformat(),
            'results': None
        }
        
        if collect_results:
            print("\n📥 COLLECTE DES RÉSULTATS :")
            print("Après avoir exécuté le code JavaScript, copiez-collez les résultats de la console ci-dessous.")
            print("Appuyez sur Entrée deux fois pour terminer la saisie.")
            
            results_lines = []
            print("\n>>> Collez les résultats ici :")
            while True:
                line = input()
                if line == "" and len(results_lines) > 0 and results_lines[-1] == "":
                    break
                results_lines.append(line)
            
            # Supprimer la dernière ligne vide
            if results_lines and results_lines[-1] == "":
                results_lines.pop()
            
            snippet_data['results'] = '\n'.join(results_lines)
            self.log(f"Résultats JavaScript collectés ({len(results_lines)} lignes)", "SUCCESS")
        
        self.report_data['javascript_results'].append(snippet_data)
        return snippet_data

    def backend_verification(self, verification_name, commands):
        """Exécuter vérifications backend automatiques"""
        self.log(f"Vérification backend : {verification_name}", "VALIDATION")
        
        verification_data = {
            'name': verification_name,
            'timestamp': datetime.now().isoformat(),
            'commands': [],
            'success': True
        }
        
        for cmd_info in commands:
            cmd_type = cmd_info['type']  # 'wp-cli', 'sql', 'ssh'
            command = cmd_info['command']
            expected = cmd_info.get('expected', None)
            description = cmd_info.get('description', command)
            
            self.log(f"  Exécution : {description}")
            
            if cmd_type == 'wp-cli':
                output = self.execute_wp_command(command)
            elif cmd_type == 'sql':
                escaped_query = command.replace('"', '\\"')
                wp_command = f'db query "{escaped_query}"'
                output = self.execute_wp_command(wp_command)
            elif cmd_type == 'ssh':
                output = self.ssh.execute_command(command) if self.ssh else None
            else:
                output = None
                self.log(f"Type de commande non supporté: {cmd_type}", "ERROR")
            
            cmd_result = {
                'type': cmd_type,
                'command': command,
                'description': description,
                'output': output,
                'success': output is not None
            }
            
            # Vérification des résultats attendus
            if expected and output:
                if isinstance(expected, str):
                    cmd_result['success'] = expected in output
                elif isinstance(expected, list):
                    cmd_result['success'] = all(exp in output for exp in expected)
                elif callable(expected):
                    cmd_result['success'] = expected(output)
            
            verification_data['commands'].append(cmd_result)
            
            if cmd_result['success']:
                self.log(f"  ✅ {description}", "SUCCESS")
            else:
                self.log(f"  ❌ {description}", "ERROR")
                verification_data['success'] = False
        
        self.report_data['backend_verifications'].append(verification_data)
        return verification_data['success']

    def generate_report(self):
        """Génération du rapport de test E2E"""
        report_dir = os.path.normpath(os.path.join(
            os.path.dirname(__file__), '..', '..', '..', '..', 'Tests', 'reporting'
        ))
        os.makedirs(report_dir, exist_ok=True)

        timestamp = datetime.now().strftime("%d-%m-%Y_%H-%M")
        report_filename = f"rapport_e2e_{self.test_name.lower().replace(' ', '_')}_{timestamp}.md"
        report_path = os.path.join(report_dir, report_filename)

        # Calcul des scores
        completed_phases = sum(1 for result in self.phases_completed if result)
        total_phases = len(self.phases_completed)
        success_verifications = len([v for v in self.report_data['backend_verifications'] if v['success']])
        total_verifications = len(self.report_data['backend_verifications'])
        
        global_score = 0
        if total_verifications > 0:
            global_score = (success_verifications / total_verifications) * 100

        # Contenu du rapport
        markdown_content = f"""# 📊 Rapport E2E - {self.test_name}

## 🎯 Score Global : {global_score:.1f}/100

| Métrique | Valeur | Statut |
|----------|--------|--------|
| **Phases Complétées** | {completed_phases}/{total_phases} | {'✅' if completed_phases == total_phases else '⚠️'} |
| **Vérifications Backend** | {success_verifications}/{total_verifications} | {'✅' if success_verifications == total_verifications else '❌'} |
| **Tests JavaScript** | {len(self.report_data['javascript_results'])} | {'✅' if len(self.report_data['javascript_results']) > 0 else '⚠️'} |
| **Observations Collectées** | {len(self.report_data['user_observations'])} | {'✅' if len(self.report_data['user_observations']) > 0 else '⚠️'} |
| **Durée Totale** | {(datetime.now() - self.start_time).total_seconds():.1f}s | ℹ️ |

## 📋 Détails des Vérifications Backend

"""

        # Détail des vérifications backend
        for i, verification in enumerate(self.report_data['backend_verifications'], 1):
            status = "✅ RÉUSSI" if verification['success'] else "❌ ÉCHEC"
            markdown_content += f"### {i}. {verification['name']} {status}\n\n"
            
            for cmd in verification['commands']:
                cmd_status = "✅" if cmd['success'] else "❌"
                markdown_content += f"- {cmd_status} **{cmd['description']}**\n"
                if cmd['output']:
                    output_preview = cmd['output'][:200] + "..." if len(cmd['output']) > 200 else cmd['output']
                    markdown_content += f"  ```\n  {output_preview}\n  ```\n"
            markdown_content += "\n"

        # Observations utilisateur
        if self.report_data.get('user_observations'):
            markdown_content += "## 👁️ Observations Utilisateur\n\n"
            for obs_data in self.report_data['user_observations']:
                markdown_content += f"### {obs_data['phase']}\n\n"
                for key, value in obs_data['observations'].items():
                    if value:
                        status_icon = "✅" if value.lower() in ['oui', 'yes', 'ok'] else ("❌" if value.lower() in ['non', 'no'] else "📝")
                        markdown_content += f"- {status_icon} **{key}** : {value}\n"
                markdown_content += "\n"

        # Résultats JavaScript
        if self.report_data['javascript_results']:
            markdown_content += "## 📜 Tests JavaScript Console\n\n"
            for js_result in self.report_data['javascript_results']:
                markdown_content += f"### {js_result['name']}\n\n"
                markdown_content += "**Code exécuté :**\n"
                markdown_content += f"```javascript\n{js_result['code']}\n```\n\n"
                
                if js_result.get('results'):
                    markdown_content += "**Résultats obtenus :**\n"
                    markdown_content += f"```\n{js_result['results']}\n```\n\n"

        # Métadonnées
        markdown_content += f"""## 📊 Métadonnées

- **Fichier de rapport** : `{report_filename}`
- **Généré le** : {datetime.now().strftime('%d/%m/%Y à %H:%M:%S')}
- **Type de test** : E2E (End-to-End)
- **Framework** : Python E2E basé sur PTI_001_2.py

---

_Rapport généré automatiquement par le framework E2E TB-Web_
"""

        # Écriture du fichier
        try:
            with open(report_path, 'w', encoding='utf-8') as f:
                f.write(markdown_content)

            self.log(f"📊 Rapport E2E généré : {report_path}", "SUCCESS")
            return report_path

        except Exception as e:
            self.log(f"❌ Erreur génération rapport : {e}", "ERROR")
            return None

    @abstractmethod
    def define_test_phases(self):
        """
        À implémenter dans chaque test E2E
        Doit retourner une liste de tuples (nom_phase, fonction_phase)
        """
        pass

    def run_test(self):
        """Orchestrateur principal du test E2E"""
        
        print("\n" + "="*80)
        print(f"🚀 TEST E2E - {self.test_name.upper()}")
        print("Plugin WC Qualiopi Steps - Framework E2E")
        print("="*80)
        
        # Chargement config et connexion
        if not self.load_config():
            return False

        if not self.connect_ssh():
            return False

        try:
            self.log(f"🚀 Démarrage du test E2E : {self.test_name}")
            
            # Métadonnées rapport
            self.report_data['meta'] = {
                'test_name': self.test_name,
                'start_time': self.start_time.isoformat(),
                'framework_version': '1.0.0'
            }

            # Exécution séquentielle des phases
            phases = self.define_test_phases()

            for phase_name, phase_func in phases:
                try:
                    result = phase_func()
                    self.phases_completed.append(result)
                    
                    if result:
                        self.log(f"{phase_name} terminée avec succès", "SUCCESS")
                    else:
                        self.log(f"{phase_name} échouée ou ignorée", "WARNING")
                        
                except Exception as e:
                    self.log(f"Erreur dans {phase_name}: {e}", "ERROR")
                    self.phases_completed.append(False)

            # Génération du rapport final
            self.log("\n📊 Génération du rapport E2E...")
            report_path = self.generate_report()
            
            if report_path:
                self.log(f"📊 Rapport disponible: {report_path}")

            # Résumé final
            completed_count = sum(1 for result in self.phases_completed if result)
            total_count = len(phases)
            success_rate = (completed_count / total_count) * 100 if total_count > 0 else 0

            print("\n" + "="*80)
            self.log("📊 RÉSUMÉ FINAL DU TEST E2E")
            self.log(f"Phases complétées: {completed_count}/{total_count}")
            self.log(f"Taux de réussite: {success_rate:.1f}%")
            
            if success_rate == 100:
                self.log("🎉 Test E2E ENTIÈREMENT RÉUSSI !", "SUCCESS")
                return True
            elif success_rate >= 80:
                self.log("⚠️ Test majoritairement fonctionnel - ajustements mineurs", "WARNING")
                return True
            else:
                self.log("❌ Test défaillant - corrections majeures nécessaires", "ERROR")
                return False

        finally:
            self.disconnect_ssh()


# ==========================================
# EXEMPLE D'UTILISATION DU FRAMEWORK
# ==========================================

class CartGuardWorkflowE2ETest(E2ETestFramework):
    """
    Exemple concret d'un test E2E pour Cart Guard
    Remplace les tests d'intégration complexes
    """
    
    def __init__(self):
        super().__init__("Cart Guard Workflow")
        self.test_product_id = 123
        
    def define_test_phases(self):
        """Définition des phases du test Cart Guard"""
        return [
            ("Configuration Admin", self.phase_configuration_admin),
            ("Test Frontend Sans Validation", self.phase_test_frontend),
            ("Simulation Validation", self.phase_simulation_validation),
            ("Vérification Post-Validation", self.phase_verification_finale)
        ]
    
    def phase_configuration_admin(self):
        """Phase 1 : Configuration du mapping admin"""
        instructions = [
            "Connectez-vous à l'admin WordPress (/wp-admin/)",
            "Naviguez vers 'Réglages > WC Qualiopi Steps'",
            f"Créez un mapping : Produit #{self.test_product_id} → Page de test",
            "Cochez la case 'Activer le blocage du panier' (ou similaire)",
            "Cliquez sur 'Enregistrer les modifications'",
            "Observez l'interface et les messages affichés"
        ]
        
        if self.wait_for_user_action("Configuration Admin", instructions):
            questions = [
                ("mapping_cree", "Le mapping a-t-il été créé avec succès ? (oui/non)"),
                ("message_confirmation", "Avez-vous vu un message de confirmation après sauvegarde ? (oui/non)"),
                ("interface_claire", "L'interface admin était-elle claire et facile à utiliser ? (oui/non)"),
                ("erreurs", "Avez-vous rencontré des erreurs ? (décrivez ou 'aucune')")
            ]
            
            self.collect_user_observations("Configuration Admin", questions)
            
            # Vérifications backend
            commands = [
                {
                    'type': 'wp-cli',
                    'command': 'option get wcqs_flags --format=json',
                    'description': 'Vérifier flag enforce_cart activé',
                    'expected': '"enforce_cart":true'
                }
            ]
            
            return self.backend_verification("Configuration Sauvegardée", commands)
        return False
    
    def phase_test_frontend(self):
        """Phase 2 : Test frontend sans validation"""
        instructions = [
            f"Allez sur la fiche du produit #{self.test_product_id}",
            "Ajoutez le produit au panier",
            "Naviguez vers la page panier",
            "Observez l'interface : bouton Commander présent ? Message test requis ?"
        ]
        
        if self.wait_for_user_action("Test Frontend", instructions):
            questions = [
                ("bouton_commander", "Y a-t-il un bouton 'Commander' ou 'Procéder au checkout' visible ? (oui/non)"),
                ("message_test", "Y a-t-il un message indiquant qu'un test est requis ? (oui/non)"),
                ("bouton_test", "Y a-t-il un bouton 'Passer le test' ou similaire ? (oui/non)"),
                ("aspect_general", "Comment décririez-vous l'aspect général de la page ? (normal/confus/clair)")
            ]
            
            return len(self.collect_user_observations("Frontend Sans Validation", questions)) > 0
        return False
    
    def phase_simulation_validation(self):
        """Phase 3 : Simulation validation test"""
        js_code = f'''// Simulation validation test
console.log("=== SIMULATION VALIDATION TEST ===");
const productId = {self.test_product_id};

// Simuler validation côté session
sessionStorage.setItem(`wcqs_test_solved_${{productId}}`, Date.now());
console.log("✓ Session marquée côté client");

console.log("Rafraîchissez la page panier pour voir les changements");'''
        
        self.display_javascript_snippet("Simulation Validation", js_code)
        
        # Simulation backend
        backend_cmd = f'eval "WcQualiopiSteps\\\\Utils\\\\WCQS_Session::set_solved({self.test_product_id}, 3600); echo \'Validation simulée\';"'
        output = self.execute_wp_command(backend_cmd)
        
        if output and 'simulée' in output:
            self.log("Validation simulée côté backend", "SUCCESS")
            return True
        return False
    
    def phase_verification_finale(self):
        """Phase 4 : Vérification état final"""
        instructions = [
            "Rafraîchissez la page panier",
            "Observez les changements :",
            "- Le bouton 'Commander' est-il maintenant visible ?",
            "- Y a-t-il un message de succès ?",
            "- L'interface est-elle cohérente ?"
        ]
        
        if self.wait_for_user_action("Vérification Finale", instructions):
            questions = [
                ("bouton_visible", "Le bouton 'Commander' est-il maintenant visible ? (oui/non)"),
                ("changements_observes", "Quels changements avez-vous observés après rafraîchissement ? (décrivez)"),
                ("workflow_coherent", "Le processus global vous semble-t-il logique et cohérent ? (oui/non)"),
                ("satisfaction", "Êtes-vous satisfait de cette expérience utilisateur ? (oui/non/moyen)")
            ]
            
            self.collect_user_observations("Post-Validation", questions)
            
            # Vérification backend finale
            commands = [
                {
                    'type': 'wp-cli',
                    'command': f'eval "echo WcQualiopiSteps\\\\Utils\\\\WCQS_Session::is_solved({self.test_product_id}) ? \'SOLVED\' : \'NOT_SOLVED\';"',
                    'description': 'Vérifier session marquée comme résolue',
                    'expected': 'SOLVED'
                }
            ]
            
            return self.backend_verification("État Final Vérifié", commands)
        return False


# Point d'entrée pour test d'exemple
if __name__ == "__main__":
    print("🧪 Framework E2E - Test d'exemple Cart Guard")
    print("Basé sur le modèle PTI_001_2.py")
    
    test = CartGuardWorkflowE2ETest()
    success = test.run_test()
    sys.exit(0 if success else 1)
