#!/usr/bin/env python3
"""
Setup script for time tracking folder structure.
Creates timetracking directory with folders per git handle.
"""

import os
import subprocess
from pathlib import Path

def get_git_handles_from_repos(repo_paths):
    """Extract all unique git handles from commits in repositories."""
    handles = set()
    for repo_path in repo_paths:
        if not os.path.exists(repo_path):
            continue
        try:
            result = subprocess.run(
                ['git', 'log', '--all', '--since=2020-01-01', '--pretty=format:%an|%ae', '--date=iso'],
                cwd=repo_path,
                capture_output=True,
                text=True,
                check=True
            )
            for line in result.stdout.strip().split('\n'):
                if '|' in line:
                    name, email = line.split('|', 1)
                    handles.add(name)
                    handles.add(email)
                    # Also add username part of email
                    if '@' in email:
                        handles.add(email.split('@')[0])
        except subprocess.CalledProcessError:
            continue
    return sorted(handles)

def setup_time_tracking_structure():
    """Create time tracking folder structure."""
    base_path = Path('/home/rubenlinde/nextcloud-docker-dev/workspace/server/apps-extra')
    timetracking_dir = base_path / 'timetracking'
    
    # Default ConductionNL repositories
    repo_paths = [
        str(base_path / 'openregister'),
        str(base_path / 'opencatalogi'),
        str(base_path / 'openconnector'),
        str(base_path / 'softwarecatalog'),
    ]
    
    # Filter existing repositories
    repo_paths = [r for r in repo_paths if os.path.exists(r)]
    
    print("Detecting git handles from repositories...")
    git_handles = get_git_handles_from_repos(repo_paths)
    
    print(f"Found {len(git_handles)} git handles:")
    for handle in git_handles:
        print(f"  - {handle}")
    
    # Create timetracking directory
    timetracking_dir.mkdir(exist_ok=True)
    print(f"\nCreated timetracking directory: {timetracking_dir}")
    
    # Create folder for each git handle
    for git_handle in git_handles:
        handle_dir = timetracking_dir / git_handle.replace('@', '_').replace(' ', '_')
        handle_dir.mkdir(exist_ok=True)
        
        # Create placeholder README
        readme_file = handle_dir / 'README.md'
        if not readme_file.exists():
            with open(readme_file, 'w') as f:
                f.write(f"# Time Tracking for {git_handle}\n\n")
                f.write("This folder contains time tracking files generated from git commit history.\n\n")
                f.write("Files:\n")
                f.write("- `{git_handle}_normal_time.csv` - Normal working hours tracking\n")
                f.write("- `{git_handle}_overtime.csv` - Overtime hours tracking (evenings/weekends)\n")
                f.write("- `{git_handle}_summary.txt` - Summary statistics\n\n")
                f.write("To generate these files, run:\n")
                f.write("```bash\n")
                f.write(f"python3 generate_time_tracking.py --git-handles {git_handle}\n")
                f.write("```\n")
        
        print(f"  Created folder: {handle_dir}")
    
    # Create .gitignore if it doesn't exist or update it
    gitignore_file = base_path / '.gitignore'
    gitignore_content = ""
    
    if gitignore_file.exists():
        with open(gitignore_file, 'r') as f:
            gitignore_content = f.read()
    
    if 'timetracking' not in gitignore_content:
        with open(gitignore_file, 'a') as f:
            if gitignore_content and not gitignore_content.endswith('\n'):
                f.write('\n')
            f.write("# Time tracking files (generated from git history)\n")
            f.write("timetracking/\n")
        print(f"\nUpdated .gitignore to exclude timetracking/")
    
    print("\nSetup complete!")
    print(f"\nTo generate time tracking files, run:")
    print(f"  python3 generate_time_tracking.py --git-handles <handle1> <handle2> ...")
    print(f"\nOr to auto-detect and process all handles:")
    print(f"  python3 generate_time_tracking.py --auto-detect-handles")

if __name__ == '__main__':
    setup_time_tracking_structure()


