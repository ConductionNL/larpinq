#!/usr/bin/env python3
import json
import subprocess
from pathlib import Path

# Repos we scanned (from summary)
scanned = {
    'openregister': 2155,
    'opencatalogi': 310,
    'openconnector': 191,
    'softwarecatalog': 158,
    'docudesk': 135
}

# Repos active in 2025 on GitHub
github_2025 = [
    'Softwarecatalogus',
    'OpenAnonymiser',
    'test-configuration',
    'opencatalogi',
    'openwoo-app-website',
    'productenendienstencatalogus',
    'woo-website',
    'openregister',
    'woo-website-template-apiv2',
    'conduction-components',
    'openconnector',
    'conduction-theme',
    'huwelijksplanner',
    'Begrafenisplanner',
    'Dimpact-OpenFormulieren-Configuraties'
]

print("REPOSITORY COMPARISON")
print("=" * 60)
print(f"Scanned locally: {len(scanned)} repos")
print(f"Active on GitHub (2025): {len(github_2025)} repos")
print()

# Normalize names for comparison
scanned_normalized = {name.lower().replace('-', ''): commits for name, commits in scanned.items()}
github_normalized = [name.lower().replace('-', '') for name in github_2025]

print("✓ FOUND LOCALLY:")
print("-" * 60)
for name, commits in scanned.items():
    print(f"  {name:<30} {commits:>5} commits")

print()
print("? POTENTIALLY MISSING (on GitHub but not in local scan):")
print("-" * 60)
missing = []
for gh_name in github_2025:
    normalized = gh_name.lower().replace('-', '')
    if normalized not in scanned_normalized:
        missing.append(gh_name)
        print(f"  {gh_name}")

if not missing:
    print("  (None - all GitHub repos were scanned)")

print()
print(f"Total potentially missing: {len(missing)} repos")
