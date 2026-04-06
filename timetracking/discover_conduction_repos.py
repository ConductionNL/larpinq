#!/usr/bin/env python3
"""
Discover all ConductionNL repositories on the system.
Searches for git repositories and filters for ConductionNL ones.
"""

import os
import subprocess
from pathlib import Path

def find_git_repos(base_paths):
    """Find all git repositories in given base paths."""
    repos = []
    
    for base_path in base_paths:
        if not os.path.exists(base_path):
            continue
        
        # Search for .git directories
        for root, dirs, files in os.walk(base_path):
            # Skip hidden directories
            dirs[:] = [d for d in dirs if not d.startswith('.')]
            
            if '.git' in dirs:
                repo_path = root
                # Check if it's a ConductionNL repository
                try:
                    result = subprocess.run(
                        ['git', 'remote', 'get-url', 'origin'],
                        cwd=repo_path,
                        capture_output=True,
                        text=True,
                        check=True
                    )
                    remote_url = result.stdout.strip()
                    if 'conductionnl' in remote_url.lower() or 'conduction' in remote_url.lower():
                        repos.append(repo_path)
                except:
                    # Try to get repo name from directory
                    repo_name = os.path.basename(repo_path)
                    if any(keyword in repo_name.lower() for keyword in ['open', 'woo', 'catalog', 'connector', 'register']):
                        repos.append(repo_path)
    
    return repos

def get_repo_name(repo_path):
    """Get repository name from git remote or directory name."""
    try:
        result = subprocess.run(
            ['git', 'remote', 'get-url', 'origin'],
            cwd=repo_path,
            capture_output=True,
            text=True,
            check=True
        )
        remote_url = result.stdout.strip()
        # Extract repo name from URL
        if 'github.com' in remote_url:
            parts = remote_url.split('/')
            repo_name = parts[-1].replace('.git', '')
            return repo_name
    except:
        pass
    
    return os.path.basename(repo_path)

def main():
    # Common locations to search
    search_paths = [
        '/home/rubenlinde/nextcloud-docker-dev/workspace/server/apps-extra',
        '/home/rubenlinde',
        '/home/rubenlinde/workspace',
        '/home/rubenlinde/projects',
    ]
    
    print("Discovering ConductionNL repositories...")
    repos = find_git_repos(search_paths)
    
    # Remove duplicates
    repos = list(set(repos))
    
    # Sort by name
    repos.sort(key=lambda x: get_repo_name(x).lower())
    
    print(f"\nFound {len(repos)} ConductionNL repositories:")
    for repo in repos:
        repo_name = get_repo_name(repo)
        print(f"  - {repo_name}: {repo}")
    
    # Write to file for use by other scripts
    output_file = Path('/home/rubenlinde/nextcloud-docker-dev/workspace/server/apps-extra/conduction_repos.txt')
    with open(output_file, 'w') as f:
        for repo in repos:
            f.write(f"{repo}\n")
    
    print(f"\nRepository list saved to: {output_file}")
    return repos

if __name__ == '__main__':
    main()


