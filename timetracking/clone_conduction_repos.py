#!/usr/bin/env python3
"""
Clone missing ConductionNL repositories for time tracking.
"""

import os
import subprocess
from pathlib import Path

def clone_repo(repo_name, base_path):
    """Clone a ConductionNL repository if it doesn't exist."""
    repo_path = base_path / repo_name
    
    if os.path.exists(repo_path):
        if os.path.exists(repo_path / '.git'):
            print(f"✓ {repo_name} already exists at {repo_path}")
            return True
        else:
            print(f"⚠ {repo_name} exists but is not a git repository")
            return False
    
    repo_url = f"https://github.com/ConductionNL/{repo_name}.git"
    print(f"Cloning {repo_name} from {repo_url}...")
    
    try:
        subprocess.run(
            ['git', 'clone', repo_url, str(repo_path)],
            check=True,
            capture_output=True
        )
        print(f"✓ Successfully cloned {repo_name}")
        return True
    except subprocess.CalledProcessError as e:
        print(f"✗ Failed to clone {repo_name}: {e}")
        return False

def main():
    base_path = Path('/home/rubenlinde/nextcloud-docker-dev/workspace/server/apps-extra')
    
    # Read repository list from config
    config_file = base_path / 'conduction_repos_config.txt'
    repo_names = []
    
    if config_file.exists():
        with open(config_file, 'r') as f:
            for line in f:
                line = line.strip()
                if line and not line.startswith('#'):
                    repo_names.append(line)
    else:
        print("Config file not found. Using default repositories.")
        repo_names = [
            'openregister',
            'opencatalogi',
            'openconnector',
            'softwarecatalog',
            'tilburg-woo-ui',
            'docudesk',
            'larpingapp',
        ]
    
    print(f"Checking {len(repo_names)} ConductionNL repositories...\n")
    
    cloned = 0
    existing = 0
    failed = 0
    
    for repo_name in repo_names:
        if clone_repo(repo_name, base_path):
            if os.path.exists(base_path / repo_name / '.git'):
                existing += 1
            else:
                cloned += 1
        else:
            failed += 1
    
    print(f"\nSummary:")
    print(f"  Existing: {existing}")
    print(f"  Cloned: {cloned}")
    print(f"  Failed: {failed}")

if __name__ == '__main__':
    main()


