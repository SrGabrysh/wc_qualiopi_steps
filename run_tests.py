#!/usr/bin/env python3
"""
Script pour lancer les tests unitaires avec génération de rapports
Compatible avec VS Code "Run" button et génération automatique de rapports JSON/HTML
"""

import subprocess
import json
import os
import sys
from datetime import datetime
from pathlib import Path
import re

class TestRunner:
    def __init__(self):
        self.plugin_dir = Path(__file__).parent
        self.reports_dir = self.plugin_dir / "test_reports"
        self.reports_dir.mkdir(exist_ok=True)
        
    def run_tests(self, test_type="all"):
        """Lance les tests et génère les rapports"""
        
        print("Lancement des tests unitaires WC Qualiopi Steps...")
        print("=" * 60)
        
        # Commande selon le type de test
        if test_type == "unit":
            cmd = ["composer", "test:unit"]
            test_name = "Unit Tests"
        elif test_type == "integration":
            cmd = ["composer", "test:integration"]
            test_name = "Integration Tests"
        else:
            cmd = ["composer", "test"]
            test_name = "All Tests"
        
        # Exécution des tests
        start_time = datetime.now()
        
        try:
            # Changer vers le répertoire du plugin
            os.chdir(self.plugin_dir)
            
            # Lancer les tests avec capture de sortie
            result = subprocess.run(
                cmd,
                capture_output=True,
                text=True,
                encoding='utf-8',
                errors='replace'
            )
            
            end_time = datetime.now()
            duration = (end_time - start_time).total_seconds()
            
            # Parser les résultats
            test_results = self._parse_test_output(result.stdout, result.stderr)
            test_results.update({
                'command': ' '.join(cmd),
                'exit_code': result.returncode,
                'duration': duration,
                'timestamp': start_time.isoformat(),
                'test_type': test_name
            })
            
            # Générer les rapports
            self._generate_reports(test_results)
            
            # Afficher le résumé
            self._display_summary(test_results)
            
            return result.returncode == 0
            
        except Exception as e:
            print(f"❌ Erreur lors de l'exécution: {e}")
            return False
    
    def _parse_test_output(self, stdout, stderr):
        """Parse la sortie des tests Pest"""
        
        results = {
            'passed': 0,
            'failed': 0,
            'total': 0,
            'assertions': 0,
            'success_rate': 0.0,
            'errors': [],
            'output': stdout,
            'stderr': stderr,
            'test_details': []
        }
        
        # Patterns pour parser la sortie Pest
        patterns = {
            'test_summary': r'Tests:\s*(\d+)\s*passed(?:,\s*(\d+)\s*failed)?\s*\((\d+)\s*assertions\)',
            'failed_test': r'FAILED\s+(.+?)\s+>\s+(.+?)\s+Error',
            'passed_test': r'✓\s+(.+?)(?:\s+[\d.]+s)?\s*$',
            'duration': r'Duration:\s+([\d.]+)s'
        }
        
        # Extraire le résumé des tests
        summary_match = re.search(patterns['test_summary'], stdout)
        if summary_match:
            results['passed'] = int(summary_match.group(1))
            results['failed'] = int(summary_match.group(2) or 0)
            results['assertions'] = int(summary_match.group(3))
            results['total'] = results['passed'] + results['failed']
            
            if results['total'] > 0:
                results['success_rate'] = (results['passed'] / results['total']) * 100
        
        # Extraire les tests échoués
        for match in re.finditer(patterns['failed_test'], stdout, re.MULTILINE):
            results['errors'].append({
                'suite': match.group(1),
                'test': match.group(2),
                'type': 'failure'
            })
        
        # Extraire les détails des tests passés
        for match in re.finditer(patterns['passed_test'], stdout, re.MULTILINE):
            results['test_details'].append({
                'name': match.group(1),
                'status': 'passed'
            })
        
        return results
    
    def _generate_reports(self, results):
        """Génère les rapports JSON et HTML"""
        
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        
        # Rapport JSON
        json_file = self.reports_dir / f"test_results_{timestamp}.json"
        with open(json_file, 'w', encoding='utf-8') as f:
            json.dump(results, f, indent=2, ensure_ascii=False)
        
        # Rapport HTML
        html_file = self.reports_dir / f"test_report_{timestamp}.html"
        html_content = self._generate_html_report(results)
        with open(html_file, 'w', encoding='utf-8') as f:
            f.write(html_content)
        
        print(f"\nRapports générés:")
        print(f"   JSON: {json_file}")
        print(f"   HTML: {html_file}")
        
        # Lien vers le rapport HTML le plus récent
        latest_html = self.reports_dir / "latest_report.html"
        if latest_html.exists():
            latest_html.unlink()
        latest_html.symlink_to(html_file.name)
        
        return json_file, html_file
    
    def _generate_html_report(self, results):
        """Génère un rapport HTML stylé"""
        
        status_color = "green" if results['failed'] == 0 else "red"
        success_rate = results['success_rate']
        
        html = f"""
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapport de Tests - WC Qualiopi Steps</title>
    <style>
        body {{ font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }}
        .container {{ max-width: 1200px; margin: 0 auto; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }}
        .header {{ background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 8px 8px 0 0; }}
        .header h1 {{ margin: 0; font-size: 2em; }}
        .header p {{ margin: 10px 0 0 0; opacity: 0.9; }}
        .stats {{ display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; padding: 30px; }}
        .stat-card {{ background: #f8f9fa; border-radius: 8px; padding: 20px; text-align: center; border-left: 4px solid #007bff; }}
        .stat-card.success {{ border-left-color: #28a745; }}
        .stat-card.danger {{ border-left-color: #dc3545; }}
        .stat-card.warning {{ border-left-color: #ffc107; }}
        .stat-number {{ font-size: 2.5em; font-weight: bold; margin: 0; }}
        .stat-label {{ color: #666; margin: 10px 0 0 0; font-weight: 500; }}
        .progress-bar {{ background: #e9ecef; border-radius: 10px; height: 20px; margin: 20px 0; overflow: hidden; }}
        .progress-fill {{ height: 100%; background: linear-gradient(90deg, #28a745, #20c997); transition: width 0.3s ease; }}
        .section {{ padding: 0 30px 30px 30px; }}
        .section h2 {{ color: #495057; border-bottom: 2px solid #e9ecef; padding-bottom: 10px; }}
        .error-list {{ background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; padding: 15px; }}
        .error-item {{ margin: 10px 0; padding: 10px; background: white; border-radius: 4px; }}
        .test-output {{ background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 4px; padding: 15px; font-family: monospace; white-space: pre-wrap; max-height: 400px; overflow-y: auto; }}
        .success-badge {{ background: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-size: 0.8em; }}
        .failure-badge {{ background: #f8d7da; color: #721c24; padding: 4px 8px; border-radius: 4px; font-size: 0.8em; }}
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧪 Rapport de Tests WC Qualiopi Steps</h1>
            <p>{results['test_type']} • {results['timestamp']} • Durée: {results['duration']:.2f}s</p>
        </div>
        
        <div class="stats">
            <div class="stat-card success">
                <div class="stat-number" style="color: #28a745;">{results['passed']}</div>
                <div class="stat-label">Tests Réussis</div>
            </div>
            <div class="stat-card danger">
                <div class="stat-number" style="color: #dc3545;">{results['failed']}</div>
                <div class="stat-label">Tests Échoués</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #007bff;">{success_rate:.1f}%</div>
                <div class="stat-label">Taux de Réussite</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-number" style="color: #ffc107;">{results['assertions']}</div>
                <div class="stat-label">Assertions</div>
            </div>
        </div>
        
        <div class="section">
            <div class="progress-bar">
                <div class="progress-fill" style="width: {success_rate}%;"></div>
            </div>
        </div>
        """
        
        # Section des erreurs
        if results['errors']:
            html += """
        <div class="section">
            <h2>❌ Tests Échoués</h2>
            <div class="error-list">
            """
            for error in results['errors']:
                html += f"""
                <div class="error-item">
                    <strong>{error['suite']}</strong> → {error['test']}
                    <span class="failure-badge">ÉCHEC</span>
                </div>
                """
            html += "</div></div>"
        
        # Section de la sortie
        html += f"""
        <div class="section">
            <h2>📋 Sortie des Tests</h2>
            <div class="test-output">{results['output']}</div>
        </div>
        
        <div class="section">
            <h2>ℹ️ Informations Système</h2>
            <p><strong>Commande:</strong> {results['command']}</p>
            <p><strong>Code de sortie:</strong> {results['exit_code']}</p>
            <p><strong>Durée totale:</strong> {results['duration']:.2f}s</p>
        </div>
    </div>
</body>
</html>
        """
        
        return html
    
    def _display_summary(self, results):
        """Affiche un résumé dans le terminal"""
        
        print("\n" + "=" * 60)
        print("📊 RÉSUMÉ DES TESTS")
        print("=" * 60)
        print(f"✅ Tests réussis:     {results['passed']}")
        print(f"❌ Tests échoués:     {results['failed']}")
        print(f"📈 Taux de réussite:  {results['success_rate']:.1f}%")
        print(f"🔍 Assertions:        {results['assertions']}")
        print(f"⏱️  Durée:            {results['duration']:.2f}s")
        
        if results['failed'] == 0:
            print("\n🎉 TOUS LES TESTS SONT PASSÉS !")
        else:
            print(f"\n⚠️  {results['failed']} test(s) en échec - Vérifiez les détails ci-dessus")
        
        print("=" * 60)

def main():
    """Point d'entrée principal"""
    
    runner = TestRunner()
    
    # Déterminer le type de test à partir des arguments
    test_type = "all"
    if len(sys.argv) > 1:
        arg = sys.argv[1].lower()
        if arg in ["unit", "integration", "all"]:
            test_type = arg
    
    # Lancer les tests
    success = runner.run_tests(test_type)
    
    # Code de sortie
    sys.exit(0 if success else 1)

if __name__ == "__main__":
    main()
