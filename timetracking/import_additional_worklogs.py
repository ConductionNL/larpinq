#!/usr/bin/env python3
"""
Import补充 worklogs from CSV into Jira/Tempo.
"""

import csv
import requests
import os
import sys
from datetime import datetime
import time

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

def main():
    if len(sys.argv) < 2:
        print("Usage: python3 import_补充_worklogs.py <csv_file>")
        sys.exit(1)
    
    csv_file = sys.argv[1]
    
    jira_token = os.environ.get('JIRA_API_TOKEN')
    if not jira_token:
        print("Error: Set JIRA_API_TOKEN environment variable")
        sys.exit(1)
    
    jira_url = "https://conduction.atlassian.net"
    auth_email = "ruben@conduction.nl"
    
    print("=" * 70)
    print("补充 WORKLOGS IMPORT")
    print("=" * 70)
    print()
    
    # Read CSV
    worklogs = []
    with open(csv_file, 'r', encoding='utf-8') as f:
        reader = csv.DictReader(f)
        worklogs = list(reader)
    
    print(f"Found {len(worklogs)} worklogs to import")
    print()
    
    # Confirm
    total_hours = sum(float(wl['hours']) for wl in worklogs)
    print(f"Total hours to add: {total_hours:.1f}")
    print()
    print("This will create worklogs on existing issues.")
    print("⚠️  Make sure you want to proceed!")
    print()
    
    confirm = input("Type 'YES' to continue: ")
    if confirm != 'YES':
        print("Cancelled.")
        sys.exit(0)
    
    print()
    print("=" * 70)
    print("IMPORTING...")
    print("=" * 70)
    print()
    
    # Import
    success = 0
    failed = 0
    
    for i, wl in enumerate(worklogs, 1):
        print(f"[{i}/{len(worklogs)}] {wl['date']} - {wl['issue_key']} - {wl['hours']}h...", end=' ')
        
        # Build datetime (use 10:00 as default time)
        started_dt = f"{wl['date']}T10:00:00.000+0100"
        time_seconds = int(float(wl['hours']) * 3600)
        
        worklog_id = create_jira_worklog(
            jira_url,
            wl['issue_key'],
            time_seconds,
            started_dt,
            wl['description'],
            auth_email,
            jira_token
        )
        
        if worklog_id:
            print(f"✓ (ID: {worklog_id})")
            success += 1
        else:
            print("✗ FAILED")
            failed += 1
        
        # Rate limiting
        time.sleep(0.3)
    
    print()
    print("=" * 70)
    print("IMPORT COMPLETE")
    print("=" * 70)
    print()
    print(f"Success: {success}")
    print(f"Failed: {failed}")
    print(f"Total hours added: {sum(float(wl['hours']) for wl in worklogs[:success]):.1f}")
    print()

if __name__ == '__main__':
    main()







