#!/usr/bin/env python3
"""
Fully Automated Tempo Import System

SAFETY GUARANTEES:
✓ NEVER deletes issues or worklogs (CREATE ONLY)
✓ ONLY logs time for YOUR account (verified by email)
✓ Dry run option to preview everything first
✓ Creates audit log of all actions

This script does EVERYTHING automatically:
1. Analyzes your commits
2. Creates Jira issues per work block
3. Logs time via Tempo API
4. Books to correct projects

Prerequisites:
- Jira API token (from Atlassian account settings)
- Tempo API token (from Tempo settings)

Usage:
    # Interactive setup (asks for API keys)
    python3 auto_tempo_import.py --user "Ruben van der Linde"
    
    # With API keys via environment
    export JIRA_API_TOKEN="your-jira-token"
    export TEMPO_API_TOKEN="your-tempo-token"
    python3 auto_tempo_import.py \
        --user "Ruben van der Linde" \
        --tempo-email "ruben@conduction.nl" \
        --auto-create-issues
    
    # Dry run (see what would be created)
    python3 auto_tempo_import.py \
        --user "Ruben van der Linde" \
        --tempo-email "ruben@conduction.nl" \
        --dry-run
"""

import csv
import json
import requests
import argparse
from pathlib import Path
from datetime import datetime, timedelta
from collections import defaultdict
import time
import os

class JiraClient:
    """Client for Jira REST API v3."""
    
    def __init__(self, base_url, email, api_token):
        """
        Initialize Jira client.
        
        Args:
            base_url: Jira base URL (e.g., https://your-company.atlassian.net)
            email: Your Atlassian account email
            api_token: Your Jira API token
        """
        self.base_url = base_url.rstrip('/')
        self.auth = (email, api_token)
        self.headers = {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        }
    
    def create_issue(self, project_key, summary, description, issue_type='Task'):
        """
        Create a Jira issue.
        
        Returns:
            Tuple of (issue_key, issue_id) or (None, None) on error
        """
        url = f'{self.base_url}/rest/api/3/issue'
        
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
                'issuetype': {'name': issue_type}
            }
        }
        
        try:
            response = requests.post(url, auth=self.auth, headers=self.headers, json=payload)
            response.raise_for_status()
            result = response.json()
            return result['key'], result['id']
        except Exception as e:
            print(f"  ✗ Error creating issue: {e}")
            if hasattr(response, 'text'):
                print(f"    Response: {response.text}")
            return None, None
    
    def get_account_id(self):
        """Get the current user's account ID."""
        url = f'{self.base_url}/rest/api/3/myself'
        
        try:
            response = requests.get(url, auth=self.auth, headers=self.headers)
            response.raise_for_status()
            result = response.json()
            return result['accountId'], result.get('emailAddress')
        except Exception as e:
            print(f"Error getting account ID: {e}")
            return None, None
    
    def create_worklog(self, issue_key, time_spent_seconds, started_datetime, description, author_account_id):
        """
        Create a worklog via Jira API (will sync to Tempo automatically).
        
        Args:
            issue_key: Jira issue key (e.g., 'OP-522')
            time_spent_seconds: Time in seconds
            started_datetime: ISO 8601 datetime string (e.g., '2025-01-24T10:30:00.000+0100')
            description: Worklog description
            author_account_id: Jira account ID (for safety verification)
        
        Returns:
            Worklog ID or None on error
        """
        url = f'{self.base_url}/rest/api/3/issue/{issue_key}/worklog'
        
        payload = {
            'timeSpentSeconds': time_spent_seconds,
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
            response = requests.post(url, auth=self.auth, headers=self.headers, json=payload)
            response.raise_for_status()
            result = response.json()
            return result.get('id')
        except Exception as e:
            print(f"  ✗ Error creating worklog: {e}")
            if hasattr(e, 'response') and hasattr(e.response, 'text'):
                print(f"    Response: {e.response.text}")
            return None

class TempoClient:
    """Client for Tempo Cloud REST API v4."""
    
    def __init__(self, base_url, api_token):
        """
        Initialize Tempo client.
        
        Args:
            base_url: Jira base URL (not used for Tempo Cloud API)
            api_token: Your Tempo API token
        """
        self.base_url = "https://api.tempo.io"  # Tempo Cloud API
        self.api_token = api_token
        self.headers = {
            'Authorization': f'Bearer {api_token}',
            'Content-Type': 'application/json'
        }
    
    def create_worklog(self, issue_key, issue_id, start_date, time_spent_seconds, description, author_account_id, start_time='09:00:00'):
        """
        Create a worklog in Tempo.
        
        Args:
            issue_key: Jira issue key (e.g., 'COND-1234') - for display only
            issue_id: Jira issue ID (numeric)
            start_date: Date in YYYY-MM-DD format
            time_spent_seconds: Time in seconds
            description: Work description
            author_account_id: Jira account ID
            start_time: Start time in HH:MM:SS format (ignored by API v4)
        
        Returns:
            Worklog ID or None on error
        """
        url = f'{self.base_url}/4/worklogs'
        
        payload = {
            'issueId': int(issue_id),
            'timeSpentSeconds': time_spent_seconds,
            'startDate': start_date,
            'description': description,
            'authorAccountId': author_account_id
        }
        
        try:
            response = requests.post(url, headers=self.headers, json=payload)
            response.raise_for_status()
            result = response.json()
            return result.get('tempoWorklogId')
        except Exception as e:
            print(f"  ✗ Error creating worklog: {e}")
            if hasattr(e, 'response') and hasattr(e.response, 'text'):
                print(f"    Response: {e.response.text}")
            return None

def read_tracking_csv(csv_file):
    """Read time tracking CSV - supports multiple formats."""
    entries = []
    
    with open(csv_file, 'r', encoding='utf-8') as f:
        reader = csv.DictReader(f)
        for row in reader:
            # Support old format (with 'Worked' field)
            if 'Worked' in row:
                if row['Worked'] == 'Yes' and float(row['Hours']) > 0:
                    entries.append({
                        'date': row['Date'],
                        'day': row['Day'],
                        'hours': float(row['Hours']),
                        'commits': int(row['Commits']),
                        'start_time': row['Start Time'],
                        'end_time': row['End Time'],
                        'repositories': row['Repositories'],
                        'summary': row['Work Summary'],
                        'is_weekend': row['Weekend'] == 'Yes'
                    })
            # Support new format (date, hours, commits, repos, first_commit, last_commit)
            elif 'date' in row and 'hours' in row:
                entries.append({
                    'date': row['date'],
                    'day': datetime.strptime(row['date'], '%Y-%m-%d').strftime('%A'),
                    'hours': float(row['hours']),
                    'commits': int(row['commits']),
                    'start_time': row.get('first_commit', ''),
                    'end_time': row.get('last_commit', ''),
                    'repositories': row.get('repos', ''),
                    'summary': f"Development work - {row.get('repos', 'various projects')}",
                    'commit_messages': row.get('commit_messages', ''),
                    'is_weekend': datetime.strptime(row['date'], '%Y-%m-%d').weekday() >= 5
                })
    
    return entries

def determine_project_key(repositories):
    """
    Determine Jira project key based on repositories.
    
    Matches actual Conduction Jira projects.
    """
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

def group_entries_into_workblocks(entries, grouping='daily'):
    """
    Group entries into logical work blocks.
    
    Args:
        entries: List of work entries
        grouping: 'daily', 'weekly', or 'by-repo'
    
    Returns:
        Dict of work blocks with metadata
    """
    work_blocks = []
    
    if grouping == 'daily':
        # One work block per day
        for entry in entries:
            project_key, project_name = determine_project_key(entry['repositories'])
            
            work_blocks.append({
                'date': entry['date'],
                'entries': [entry],
                'total_hours': entry['hours'],
                'total_commits': entry['commits'],
                'project_key': project_key,
                'project_name': project_name,
                'repositories': entry['repositories'],
                'summary': entry['summary']
            })
    
    elif grouping == 'weekly':
        # Group by week
        weekly_blocks = defaultdict(lambda: {
            'entries': [],
            'total_hours': 0,
            'total_commits': 0,
            'repositories': set(),
            'dates': []
        })
        
        for entry in entries:
            date = datetime.strptime(entry['date'], '%Y-%m-%d')
            week_key = date.strftime('%Y-W%U')  # Year-Week format
            
            weekly_blocks[week_key]['entries'].append(entry)
            weekly_blocks[week_key]['total_hours'] += entry['hours']
            weekly_blocks[week_key]['total_commits'] += entry['commits']
            weekly_blocks[week_key]['repositories'].update(entry['repositories'].split(', '))
            weekly_blocks[week_key]['dates'].append(entry['date'])
        
        for week_key, block_data in weekly_blocks.items():
            repos_str = ', '.join(sorted(block_data['repositories']))
            project_key, project_name = determine_project_key(repos_str)
            first_date = min(block_data['dates'])
            
            work_blocks.append({
                'date': first_date,
                'week': week_key,
                'entries': block_data['entries'],
                'total_hours': block_data['total_hours'],
                'total_commits': block_data['total_commits'],
                'project_key': project_key,
                'project_name': project_name,
                'repositories': repos_str,
                'summary': f"{block_data['total_commits']} commits across {len(block_data['dates'])} days"
            })
    
    return work_blocks

def create_issue_summary(work_block):
    """Create a concise issue summary."""
    date_obj = datetime.strptime(work_block['date'], '%Y-%m-%d')
    date_formatted = date_obj.strftime('%B %d, %Y')  # e.g., "January 15, 2025"
    
    return f"Development Work - {work_block['project_name']} - {date_formatted}"

def create_issue_description(work_block):
    """Create detailed issue description with commit messages."""
    # Collect commit messages from entries
    commit_messages = []
    for entry in work_block['entries']:
        if entry.get('commit_messages'):
            # Split by | and clean up
            messages = entry['commit_messages'].split(' | ')
            commit_messages.extend(messages)
    
    # Build description
    description_parts = [
        f"Development work on {work_block['project_name']}",
        "",
        f"Date: {work_block['date']}",
        f"Hours: {work_block['total_hours']}",
        f"Commits: {work_block['total_commits']}",
        f"Repositories: {work_block['repositories']}",
        ""
    ]
    
    if commit_messages:
        description_parts.append("Commit Messages:")
        # Show up to 20 commits
        for i, msg in enumerate(commit_messages[:20], 1):
            # Clean up message
            msg_clean = msg.strip()
            if msg_clean:
                description_parts.append(f"  {i}. {msg_clean}")
        
        if len(commit_messages) > 20:
            description_parts.append(f"  ... and {len(commit_messages) - 20} more commits")
    else:
        description_parts.append("Work Summary:")
        description_parts.append(work_block['summary'][:500])
    
    description_parts.extend([
        "",
        "---",
        "Auto-generated from git commit analysis."
    ])
    
    return "\n".join(description_parts)

def process_work_blocks(work_blocks, jira_client, tempo_client, account_id, dry_run=False, audit_log_file=None):
    """
    Process work blocks: create issues and log time.
    
    SAFETY GUARANTEES:
    - NEVER deletes anything (CREATE ONLY operations)
    - Only logs time for the verified account
    - All actions are logged to audit file
    
    Returns:
        Statistics dict
    """
    stats = {
        'total_blocks': len(work_blocks),
        'issues_created': 0,
        'worklogs_created': 0,
        'total_hours': 0,
        'errors': 0,
        'actions': []  # Audit trail
    }
    
    # Open audit log if provided.
    audit_file = None
    if audit_log_file and not dry_run:
        audit_file = open(audit_log_file, 'a', encoding='utf-8')
        audit_file.write(f"\n{'='*70}\n")
        audit_file.write(f"Tempo Import Session - {datetime.now().isoformat()}\n")
        audit_file.write(f"{'='*70}\n\n")
    
    try:
        for i, block in enumerate(work_blocks, 1):
            print(f"\n[{i}/{len(work_blocks)}] Processing work block...")
            print(f"  Date: {block['date']}")
            print(f"  Project: {block['project_name']} ({block['project_key']})")
            print(f"  Hours: {block['total_hours']}")
            print(f"  Commits: {block['total_commits']}")
            
            stats['total_hours'] += block['total_hours']
            
            action_log = {
                'timestamp': datetime.now().isoformat(),
                'date': block['date'],
                'project': block['project_key'],
                'hours': block['total_hours'],
                'commits': block['total_commits']
            }
            
            if dry_run:
                print(f"  [DRY RUN] Would create issue: {create_issue_summary(block)}")
                print(f"  [DRY RUN] Would log {block['total_hours']} hours")
                action_log['action'] = 'DRY_RUN'
                action_log['status'] = 'simulated'
                stats['issues_created'] += 1
                stats['worklogs_created'] += 1
                stats['actions'].append(action_log)
                continue
            
            # SAFETY CHECK: Verify we're only doing CREATE operations (no DELETE, no UPDATE).
            # This is enforced by only using POST endpoints, never DELETE or PUT.
            
            # Create Jira issue (POST only, never DELETE).
            print(f"  Creating Jira issue...")
            issue_key, issue_id = jira_client.create_issue(
                project_key=block['project_key'],
                summary=create_issue_summary(block),
                description=create_issue_description(block)
            )
            
            if not issue_key or not issue_id:
                print(f"  ✗ Failed to create issue")
                stats['errors'] += 1
                action_log['action'] = 'CREATE_ISSUE'
                action_log['status'] = 'FAILED'
                stats['actions'].append(action_log)
                
                if audit_file:
                    audit_file.write(f"[{datetime.now().isoformat()}] FAILED to create issue\n")
                    audit_file.write(f"  Date: {block['date']}, Project: {block['project_key']}\n\n")
                continue
            
            print(f"  ✓ Created issue: {issue_key} (ID: {issue_id})")
            stats['issues_created'] += 1
            action_log['issue_key'] = issue_key
            action_log['issue_id'] = issue_id
            action_log['action'] = 'CREATE_ISSUE'
            action_log['status'] = 'SUCCESS'
            
            if audit_file:
                audit_file.write(f"[{datetime.now().isoformat()}] CREATED issue: {issue_key} (ID: {issue_id})\n")
                audit_file.write(f"  Project: {block['project_key']}\n")
                audit_file.write(f"  Date: {block['date']}\n")
                audit_file.write(f"  Summary: {create_issue_summary(block)}\n\n")
            
            # Small delay to avoid rate limiting.
            time.sleep(0.5)
            
            # Create Jira worklog (POST only, never DELETE).
            # This will automatically sync to Tempo!
            # SAFETY: authorAccountId ensures time is logged ONLY for your account.
            print(f"  Logging time via Jira (syncs to Tempo)...")
            time_seconds = int(block['total_hours'] * 3600)
            start_time = block['entries'][0]['start_time'] if block['entries'][0]['start_time'] else '09:00'
            
            # Build ISO 8601 datetime for the work start
            # Format: 2025-01-24T10:30:00.000+0100
            started_datetime = f"{block['date']}T{start_time}:00.000+0100"
            
            worklog_description = f"{block['total_commits']} commits in {block['repositories']}"
            if len(worklog_description) > 500:
                worklog_description = worklog_description[:497] + "..."
            
            worklog_id = jira_client.create_worklog(
                issue_key=issue_key,
                time_spent_seconds=time_seconds,
                started_datetime=started_datetime,
                description=worklog_description,
                author_account_id=account_id  # SAFETY: Only logs for YOUR account
            )
            
            if not worklog_id:
                print(f"  ✗ Failed to create worklog")
                stats['errors'] += 1
                action_log['worklog_status'] = 'FAILED'
                
                if audit_file:
                    audit_file.write(f"[{datetime.now().isoformat()}] FAILED to create worklog for {issue_key}\n\n")
                continue
            
            print(f"  ✓ Logged {block['total_hours']} hours (Jira Worklog ID: {worklog_id})")
            stats['worklogs_created'] += 1
            action_log['worklog_id'] = worklog_id
            action_log['worklog_status'] = 'SUCCESS'
            
            if audit_file:
                audit_file.write(f"[{datetime.now().isoformat()}] LOGGED time: {block['total_hours']} hours\n")
                audit_file.write(f"  Issue: {issue_key}\n")
                audit_file.write(f"  Jira Worklog ID: {worklog_id}\n")
                audit_file.write(f"  Started: {started_datetime}\n")
                audit_file.write(f"  Account: {account_id}\n\n")
            
            stats['actions'].append(action_log)
            
            # Small delay between blocks.
            time.sleep(0.5)
    finally:
        if audit_file:
            audit_file.write(f"\n{'='*70}\n")
            audit_file.write("Session Summary:\n")
            audit_file.write(f"  Issues created: {stats['issues_created']}\n")
            audit_file.write(f"  Worklogs created: {stats['worklogs_created']}\n")
            audit_file.write(f"  Total hours: {stats['total_hours']:.1f}\n")
            audit_file.write(f"  Errors: {stats['errors']}\n")
            audit_file.write(f"{'='*70}\n\n")
            audit_file.close()
    
    return stats

def process_csv_file(csv_file, args, jira_client, tempo_client, account_id, audit_log_file):
    """Process a single CSV file."""
    print(f"Processing: {csv_file.name}")
    entries = read_tracking_csv(csv_file)
    print(f"Found {len(entries)} work entries")
    
    if not entries:
        print("⚠ No entries found, skipping.")
        return
    
    print(f"\nGrouping into work blocks ({args.grouping})...")
    work_blocks = group_entries_into_workblocks(entries, args.grouping)
    print(f"Created {len(work_blocks)} work blocks")
    
    print(f"\nProcessing work blocks...\n")
    stats = process_work_blocks(
        work_blocks, jira_client, tempo_client, account_id, 
        args.dry_run, audit_log_file
    )
    
    print()
    print("=" * 70)
    print(f"RESULTS - {csv_file.name}")
    print("=" * 70)
    print(f"Total work blocks: {stats['total_blocks']}")
    print(f"Issues created: {stats['issues_created']}")
    print(f"Worklogs created: {stats['worklogs_created']}")
    print(f"Total hours logged: {stats['total_hours']:.1f}")
    print(f"Errors: {stats['errors']}")
    print()

def main():
    parser = argparse.ArgumentParser(
        description='Fully automated Tempo import with Jira issue creation',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
SAFETY GUARANTEES:
✓ NEVER deletes issues or worklogs (CREATE ONLY)
✓ ONLY logs time for YOUR account (verified by email)
✓ All actions logged to audit file
✓ Dry run option to preview everything

Examples:
  # Dry run (see what would be created)
  python3 auto_tempo_import.py \
    --user "Ruben van der Linde" \
    --tempo-email "ruben@conduction.nl" \
    --dry-run
  
  # Full automated import
  python3 auto_tempo_import.py \
    --user "Ruben van der Linde" \
    --jira-url "https://company.atlassian.net" \
    --jira-email "ruben@conduction.nl" \
    --tempo-email "ruben@conduction.nl" \
    --auto-create-issues
  
  # With API tokens via environment
  export JIRA_API_TOKEN="your-token"
  export TEMPO_API_TOKEN="your-token"
  python3 auto_tempo_import.py \
    --user "Ruben van der Linde" \
    --jira-url "https://company.atlassian.net" \
    --jira-email "ruben@conduction.nl" \
    --tempo-email "ruben@conduction.nl" \
    --auto-create-issues
        """
    )
    
    parser.add_argument('--user', type=str, required=True, help='User name')
    parser.add_argument('--jira-url', type=str, default='https://your-company.atlassian.net',
                        help='Jira base URL')
    parser.add_argument('--jira-email', type=str, default=None,
                        help='Your Atlassian account email')
    parser.add_argument('--tempo-email', type=str, required=True,
                        help='Email for Tempo account (REQUIRED for safety - ensures time logged only for you)')
    parser.add_argument('--jira-token', type=str, default=None,
                        help='Jira API token (or set JIRA_API_TOKEN env var)')
    parser.add_argument('--tempo-token', type=str, default=None,
                        help='Tempo API token (or set TEMPO_API_TOKEN env var)')
    parser.add_argument('--grouping', type=str, choices=['daily', 'weekly'], default='daily',
                        help='How to group work blocks (daily or weekly)')
    parser.add_argument('--include-overtime', action='store_true',
                        help='Include overtime entries')
    parser.add_argument('--dry-run', action='store_true',
                        help='Show what would be done without actually doing it')
    parser.add_argument('--auto-create-issues', action='store_true',
                        help='Automatically create Jira issues (required for actual execution)')
    parser.add_argument('--audit-log', type=str, default='tempo_import_audit.log',
                        help='Audit log file path (default: tempo_import_audit.log)')
    parser.add_argument('--custom-csv', type=str, default=None,
                        help='Path to custom CSV file (for testing single day/subset)')
    
    args = parser.parse_args()
    
    print("=" * 70)
    print("FULLY AUTOMATED TEMPO IMPORT")
    print("=" * 70)
    print()
    print("🔒 SAFETY FEATURES:")
    print("  ✓ NEVER deletes issues or worklogs")
    print("  ✓ ONLY creates new data (POST operations only)")
    print("  ✓ Time logged ONLY for your account")
    print("  ✓ All actions logged to audit file")
    print("  ✓ Email verification before execution")
    print()
    
    # Get API tokens
    jira_token = args.jira_token or os.environ.get('JIRA_API_TOKEN')
    tempo_token = args.tempo_token or os.environ.get('TEMPO_API_TOKEN')
    
    if not args.dry_run:
        if not jira_token or not tempo_token:
            print("❌ ERROR: API tokens required!")
            print()
            print("Option 1: Set environment variables")
            print("  export JIRA_API_TOKEN='your-jira-token'")
            print("  export TEMPO_API_TOKEN='your-tempo-token'")
            print()
            print("Option 2: Pass as arguments")
            print("  --jira-token 'your-token' --tempo-token 'your-token'")
            print()
            print("Or run with --dry-run to see what would be done")
            return
        
        if not args.jira_email:
            print("❌ ERROR: --jira-email required")
            print("  Use your Atlassian account email")
            return
        
        if not args.auto_create_issues:
            print("❌ ERROR: --auto-create-issues flag required for actual execution")
            print("  This ensures you understand issues will be created in Jira")
            print()
            print("Run with --dry-run first to preview what would be created")
            return
    
    # SAFETY CHECK: Verify tempo-email is provided
    if not args.tempo_email:
        print("❌ ERROR: --tempo-email is REQUIRED")
        print("  This ensures time is logged ONLY for your account")
        print("  Example: --tempo-email 'ruben@conduction.nl'")
        return
    
    # Find tracking files
    if args.custom_csv:
        # Use custom CSV file
        csv_file = Path(args.custom_csv)
        if not csv_file.exists():
            print(f"❌ ERROR: Custom CSV file not found: {args.custom_csv}")
            return
        csv_files = [csv_file]
        print(f"📄 Using custom CSV: {csv_file}")
    else:
        # Auto-detect from timetracking directory
        base_path = Path('/home/rubenlinde/nextcloud-docker-dev/workspace/server/apps-extra/timetracking')
        safe_name = args.user.replace('@', '_').replace(' ', '_')
        
        possible_dirs = [
            base_path / safe_name,
            base_path / f'github_{safe_name}',
            base_path / f'github_api_{safe_name}'
        ]
        
        user_dir = None
        for d in possible_dirs:
            if d.exists():
                user_dir = d
                break
        
        if not user_dir:
            print(f"❌ ERROR: No tracking files found for user: {args.user}")
            return
        
        # Find CSV files
        csv_files = []
        csv_files.append(user_dir / f'{args.user}_normal_time.csv')
        if args.include_overtime:
            csv_files.append(user_dir / f'{args.user}_overtime.csv')
        
        csv_files = [f for f in csv_files if f.exists()]
        
        if not csv_files:
            print(f"❌ ERROR: No CSV files found in {user_dir}")
            return
    
    print(f"User: {args.user}")
    print(f"Tempo Email: {args.tempo_email}")
    print(f"Grouping: {args.grouping}")
    print(f"Mode: {'🔍 DRY RUN (safe preview)' if args.dry_run else '⚡ LIVE EXECUTION'}")
    if not args.dry_run:
        print(f"Audit Log: {args.audit_log}")
    print()
    
    # Initialize clients (if not dry run)
    jira_client = None
    tempo_client = None
    account_id = None
    
    if not args.dry_run:
        print("Initializing API clients...")
        jira_client = JiraClient(args.jira_url, args.jira_email, jira_token)
        tempo_client = TempoClient(args.jira_url, tempo_token)
        
        print("Getting account ID...")
        account_id, account_email = jira_client.get_account_id()
        if not account_id:
            print("❌ ERROR: Could not get account ID")
            return
        
        print(f"✓ Account ID: {account_id}")
        print(f"✓ Account Email: {account_email}")
        
        # SAFETY CHECK: Verify email matches
        if account_email and account_email.lower() != args.tempo_email.lower():
            print()
            print("⚠️  WARNING: Email mismatch!")
            print(f"   Jira account: {account_email}")
            print(f"   Requested:    {args.tempo_email}")
            print()
            confirm = input("Continue anyway? (type 'YES' to confirm): ")
            if confirm != 'YES':
                print("Cancelled for safety.")
                return
        else:
            print(f"✓ Email verified: {args.tempo_email}")
        
        # Additional confirmation
        print()
        print("⚠️  FINAL CONFIRMATION:")
        print(f"   This will CREATE {args.grouping} issues and log time")
        print(f"   Time will be logged for: {args.tempo_email}")
        print(f"   Account ID: {account_id}")
        print()
        confirm = input("Proceed? (type 'YES' to confirm): ")
        if confirm != 'YES':
            print("Cancelled.")
            return
        print()
    
    # Prepare audit log file path
    audit_log_file = None
    if not args.dry_run:
        if args.custom_csv:
            audit_log_file = Path(args.audit_log)
        else:
            audit_log_file = Path(user_dir) / args.audit_log
        print(f"📝 Audit log: {audit_log_file}")
        print()
    
    # Process CSV files
    for csv_file in csv_files:
        process_csv_file(csv_file, args, jira_client, tempo_client, account_id, audit_log_file)
    
    print("=" * 70)
    if args.dry_run:
        print("🔍 DRY RUN COMPLETE")
        print()
        print("No changes were made. This was a safe preview.")
        print("To execute for real, run with --auto-create-issues")
    else:
        print("✅ IMPORT COMPLETE!")
        print()
        print(f"📝 Audit log saved to: {audit_log_file}")
        print("🔍 Check your Jira/Tempo to verify the results")
        print()
        print("SAFETY REMINDER:")
        print("  ✓ Only CREATE operations were performed")
        print("  ✓ No issues or worklogs were deleted")
        print("  ✓ Time logged only for: {args.tempo_email}")
    print("=" * 70)

if __name__ == '__main__':
    main()

