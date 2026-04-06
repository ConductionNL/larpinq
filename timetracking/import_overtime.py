#!/usr/bin/env python3
"""
Import overtime hours from GitHub API CSV into Tempo.
Creates Jira issues specifically marked as overtime work.
"""

import csv
import sys
import os
import requests
from datetime import datetime
import time

def determine_project_key(repositories):
    """Determine Jira project key from repositories."""
    repos_lower = repositories.lower()
    
    if 'openregister' in repos_lower or 'register' in repos_lower:
        return 'REGISTERS', 'Open Register'
    elif 'opencatalogi' in repos_lower or 'catalogi' in repos_lower:
        return 'OP', 'OpenCatalogi'
    elif 'openconnector' in repos_lower or 'connector' in repos_lower:
        return 'CONNECTOR', 'OpenConnector'
    elif 'softwarecatalog' in repos_lower or 'softwarecatalogus' in repos_lower:
        return 'VSC', 'VNG Software Catalogus'
    elif 'docudesk' in repos_lower:
        return 'DOCD', 'DocuDesk'
    elif 'woo' in repos_lower:
        return 'WOO', 'Woo Development'
    elif 'rubenlinde' in repos_lower:
        return 'CNDCTN', 'Conduction General'
    else:
        return 'CNDCTN', 'Conduction General'

def create_jira_issue(jira_url, project_key, summary, description, auth_email, jira_token):
    """Create a Jira issue."""
    url = f"{jira_url}/rest/api/3/issue"
    
    payload = {
        'fields': {
            'project': {'key': project_key},
            'summary': summary,
            'description': {
                'type': 'doc',
                'version': 1,
                'content': [
                    {
                        'type': 'paragraph',
                        'content': [
                            {'type': 'text', 'text': description}
                        ]
                    }
                ]
            },
            'issuetype': {'name': 'Task'}
        }
    }
    
    try:
        response = requests.post(url, auth=(auth_email, jira_token),
                               headers={'Content-Type': 'application/json'},
                               json=payload)
        response.raise_for_status()
        result = response.json()
        return result.get('key'), result.get('id')
    except Exception as e:
        print(f"Error: {e}")
        return None, None

def create_jira_worklog(jira_url, issue_key, time_seconds, started_datetime, description, auth_email, jira_token):
    """Create a worklog via Jira API."""
    url = f"{jira_url}/rest/api/3/issue/{issue_key}/worklog"
    
    payload = {
        'timeSpentSeconds': time_seconds,
        'started': started_datetime,
        'comment': {
            'type': 'doc',
            'version': 1,
            'content': [
                {
                    'type': 'paragraph',
                    'content': [
                        {'type': 'text', 'text': description}
                    ]
                }
            ]
        }
    }
    
    try:
        response = requests.post(url, auth=(auth_email, jira_token),
                               headers={'Content-Type': 'application/json'},
                               json=payload)
        response.raise_for_status()
        return response.json().get('id')
    except Exception as e:
        return None

def create_issue_description(date, hours, commits, repos, commit_messages):
    """Create detailed issue description."""
    description_parts = [
        "🌙 OVERTIME WORK",
        "",
        f"Date: {date}",
        f"Hours: {hours}",
        f"Commits: {commits}",
        f"Repositories: {repos}",
        ""
    ]
    
    if commit_messages:
        messages = commit_messages.split(' | ')
        description_parts.append("Commit Messages:")
        for i, msg in enumerate(messages[:20], 1):
            if msg.strip():
                description_parts.append(f"  {i}. {msg.strip()}")
        
        if len(messages) > 20:
            description_parts.append(f"  ... and {len(messages) - 20} more commits")
    
    description_parts.extend([
        "",
        "---",
        "Auto-generated from git commit analysis (overtime hours)"
    ])
    
    return "\n".join(description_parts)

def main():
    if len(sys.argv) < 2:
        print("Usage: python3 import_overtime.py <overtime_csv>")
        sys.exit(1)
    
    csv_file = sys.argv[1]
    
    jira_token = os.environ.get('JIRA_API_TOKEN')
    if not jira_token:
        print("Error: Set JIRA_API_TOKEN")
        sys.exit(1)
    
    jira_url = "https://conduction.atlassian.net"
    auth_email = "ruben@conduction.nl"
    
    print("=" * 70)
    print("🌙 OVERTIME IMPORT FROM GITHUB")
    print("=" * 70)
    print()
    
    # Read CSV
    entries = []
    with open(csv_file, 'r', encoding='utf-8') as f:
        reader = csv.DictReader(f)
        entries = list(reader)
    
    print(f"Found {len(entries)} overtime entries")
    total_hours = sum(float(e['hours']) for e in entries)
    print(f"Total overtime hours: {total_hours:.1f}")
    print()
    
    # Confirm
    print("This will:")
    print("  - Create Jira issues marked as OVERTIME")
    print("  - Log time with historical timestamps")
    print("  - Include commit messages in descriptions")
    print()
    
    confirm = input("Type 'YES' to continue: ")
    if confirm != 'YES':
        print("Cancelled.")
        sys.exit(0)
    
    print()
    print("=" * 70)
    print("IMPORTING OVERTIME...")
    print("=" * 70)
    print()
    
    # Import
    success = 0
    failed = 0
    
    for i, entry in enumerate(entries, 1):
        date = entry['date']
        hours = float(entry['hours'])
        commits = int(entry['commits'])
        repos = entry['repos']
        first_commit = entry.get('first_commit', '18:00')
        commit_messages = entry.get('commit_messages', '')
        
        print(f"[{i}/{len(entries)}] {date} - {repos} - {hours}h...", end=' ')
        
        # Determine project
        project_key, project_name = determine_project_key(repos)
        
        # Create issue
        date_obj = datetime.strptime(date, '%Y-%m-%d')
        date_formatted = date_obj.strftime('%B %d, %Y')
        
        summary = f"Overtime Work - {project_name} - {date_formatted}"
        description = create_issue_description(date, hours, commits, repos, commit_messages)
        
        issue_key, issue_id = create_jira_issue(
            jira_url, project_key, summary, description,
            auth_email, jira_token
        )
        
        if not issue_key:
            print("✗ Failed to create issue")
            failed += 1
            continue
        
        print(f"Issue: {issue_key}", end=' ')
        
        # Create worklog
        time_seconds = int(hours * 3600)
        started_dt = f"{date}T{first_commit}:00.000+0100"
        worklog_desc = f"🌙 OVERTIME: {commits} commits in {repos}"
        
        worklog_id = create_jira_worklog(
            jira_url, issue_key, time_seconds, started_dt,
            worklog_desc, auth_email, jira_token
        )
        
        if worklog_id:
            print(f"✓ (Worklog: {worklog_id})")
            success += 1
        else:
            print("✗ Failed worklog")
            failed += 1
        
        # Rate limiting
        time.sleep(0.5)
    
    print()
    print("=" * 70)
    print("OVERTIME IMPORT COMPLETE")
    print("=" * 70)
    print()
    print(f"Success: {success}")
    print(f"Failed: {failed}")
    print(f"Total overtime hours added: {sum(float(e['hours']) for e in entries[:success]):.1f}")
    print()

if __name__ == '__main__':
    main()







