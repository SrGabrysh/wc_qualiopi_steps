#!/usr/bin/env python3
"""
Script rapide pour lancer les tests unitaires
Version simplifiée de run_tests.py pour exécution rapide
"""

import subprocess
import sys
import os
from pathlib import Path
from datetime import datetime

def main():
    """Lance les tests unitaires rapidement"""
    
    plugin_dir = Path(__file__).parent
    os.chdir(plugin_dir)
    
    print("🚀 Tests unitaires rapides - WC Qualiopi Steps")
    print("-" * 50)
    
    try:
        # Lancer les tests unitaires
        result = subprocess.run(
            ["composer", "test:unit"],
            text=True,
            encoding='utf-8',
            errors='replace'
        )
        
        print(f"\n⏱️  Terminé à {datetime.now().strftime('%H:%M:%S')}")
        print(f"📊 Code de sortie: {result.returncode}")
        
        if result.returncode == 0:
            print("✅ Tests réussis !")
        else:
            print("❌ Certains tests ont échoué")
        
        return result.returncode
        
    except Exception as e:
        print(f"❌ Erreur: {e}")
        return 1

if __name__ == "__main__":
    sys.exit(main())
