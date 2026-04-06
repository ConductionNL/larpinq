#!/usr/bin/env python3
"""
Analyze work hours and generate补充 worklogs based on colleague's work.

This script:
1. Fetches all your worklogs for 2025
2. Fetches all Remko's worklogs for 2025  
3. Identifies workdays where you have < 9 hours
4. Distributes missing hours across Remko's issues from those days
5. Generates a CSV for import

Target: 9 hours per workday (08:30-17:30)
"""

import requests
import os
import sys
import csv
from datetime import datetime, timedelta
from collections import defaultdict
import json

# Configuration
TARGET_HOURS_PER_DAY = 9.0
YOUR_EMAIL = "ruben@conduction.nl"
COLLEAGUE_EMAIL = "remko@conduction.nl"
PREFERRED_PROJECT = "VSC"  # Software Catalogus

def get_issue_key_from_id(jira_url, issue_id, auth_email, jira_token, cache={}):
    """Get issue key from issue ID (with caching)."""
    if issue_id in cache:
        return cache[issue_id]
    
    url = f"{jira_url}/rest/api/3/issue/{issue_id}"
    params = {'fields': 'key'}
    
    try:
        response = requests.get(url, auth=(auth_email, jira_token), params=params, timeout=5)
        if response.status_code == 200:
            key = response.json().get('key', 'UNKNOWN')
            cache[issue_id] = key
            return key
    except:
        pass
    
    cache[issue_id] = 'UNKNOWN'
    return 'UNKNOWN'

def get_account_id(jira_url, target_email, auth_email, jira_token):
    """Get Jira account ID for an email."""
    url = f"{jira_url}/rest/api/3/user/search"
    params = {'query': target_email}
    
    response = requests.get(url, auth=(auth_email, jira_token), params=params)
    if response.status_code == 200:
        users = response.json()
        for user in users:
            if user.get('emailAddress', '').lower() == target_email.lower():
                return user['accountId']
    return None

def get_all_worklogs_for_user(tempo_token, account_id, year=2025):
    """Fetch all Tempo worklogs for a user in a year."""
    url = f"https://api.tempo.io/4/worklogs/user/{account_id}"
    headers = {'Authorization': f'Bearer {tempo_token}'}
    params = {
        'from': f'{year}-01-01',
        'to': f'{year}-12-31',
        'limit': 1000
    }
    
    response = requests.get(url, headers=headers, params=params)
    if response.status_code == 200:
        return response.json().get('results', [])
    else:
        print(f"Error fetching worklogs: {response.status_code}")
        return []

def main():
    # Get API tokens
    jira_token = os.environ.get('JIRA_API_TOKEN')
    tempo_token = os.environ.get('TEMPO_API_TOKEN')
    jira_url = "https://conduction.atlassian.net"
    
    if not jira_token or not tempo_token:
        print("Error: Set JIRA_API_TOKEN and TEMPO_API_TOKEN environment variables")
        sys.exit(1)
    
    print("=" * 70)
    print("WORKLOG ANALYSIS &補充 GENERATION")
    print("=" * 70)
    print()
    
    # Step 1: Get account IDs
    print("Step 1: Getting account IDs...")
    your_account_id = get_account_id(jira_url, YOUR_EMAIL, YOUR_EMAIL, jira_token)
    colleague_account_id = get_account_id(jira_url, COLLEAGUE_EMAIL, YOUR_EMAIL, jira_token)
    
    if not your_account_id or not colleague_account_id:
        print("Error: Could not find account IDs")
        sys.exit(1)
    
    print(f"  ✓ Your account: {your_account_id}")
    print(f"  ✓ Remko's account: {colleague_account_id}")
    print()
    
    # Step 2: Fetch all worklogs
    print("Step 2: Fetching all worklogs for 2025...")
    your_worklogs = get_all_worklogs_for_user(tempo_token, your_account_id)
    colleague_worklogs = get_all_worklogs_for_user(tempo_token, colleague_account_id)
    
    print(f"  ✓ Your worklogs: {len(your_worklogs)}")
    print(f"  ✓ Remko's worklogs: {len(colleague_worklogs)}")
    print()
    
    # Step 2b: Get issue keys for colleague's worklogs
    print("Step 2b: Resolving issue keys...")
    issue_cache = {}
    for i, wl in enumerate(colleague_worklogs):
        if i % 50 == 0:
            print(f"  Progress: {i}/{len(colleague_worklogs)}", end='\r')
        if 'issue' in wl and 'id' in wl['issue']:
            get_issue_key_from_id(jira_url, wl['issue']['id'], YOUR_EMAIL, jira_token, issue_cache)
    print(f"  ✓ Resolved {len(issue_cache)} unique issues          ")
    print()
    
    # Step 3: Group by date
    print("Step 3: Analyzing hours per day...")
    your_daily_hours = defaultdict(lambda: {'hours': 0, 'worklogs': []})
    colleague_daily_work = defaultdict(lambda: {'hours': 0, 'issues': []})
    
    for wl in your_worklogs:
        date = wl['startDate']
        hours = wl['timeSpentSeconds'] / 3600
        your_daily_hours[date]['hours'] += hours
        your_daily_hours[date]['worklogs'].append(wl)
    
    for wl in colleague_worklogs:
        date = wl['startDate']
        hours = wl['timeSpentSeconds'] / 3600
        
        # Get issue info from cache
        if 'issue' in wl and 'id' in wl['issue']:
            issue_id = wl['issue']['id']
            issue_key = issue_cache.get(issue_id, 'UNKNOWN')
        else:
            issue_id = None
            issue_key = 'UNKNOWN'
        
        colleague_daily_work[date]['hours'] += hours
        colleague_daily_work[date]['issues'].append({
            'key': issue_key,
            'id': issue_id,
            'hours': hours,
            'description': wl.get('description', ''),
            'project': issue_key.split('-')[0] if '-' in issue_key else 'UNKNOWN'
        })
    
    # Step 4: Find workdays with < 9 hours
    print("Step 4: Finding days with < 9 hours...")
    print()
    
    # Get all workdays in 2025
    start_date = datetime(2025, 1, 1)
    end_date = datetime(2025, 12, 31)
    current = start_date
    
    workdays_needing_hours = []
    
    while current <= end_date:
        if current.weekday() < 5:  # Monday-Friday
            date_str = current.strftime('%Y-%m-%d')
            your_hours = your_daily_hours[date_str]['hours']
            
            if your_hours > 0 and your_hours < TARGET_HOURS_PER_DAY:
                missing_hours = TARGET_HOURS_PER_DAY - your_hours
                colleague_work = colleague_daily_work[date_str]
                
                if colleague_work['issues']:  # Remko worked that day
                    workdays_needing_hours.append({
                        'date': date_str,
                        'your_hours': your_hours,
                        'missing_hours': missing_hours,
                        'colleague_issues': colleague_work['issues']
                    })
        
        current += timedelta(days=1)
    
    print(f"Found {len(workdays_needing_hours)} days needing additional hours")
    print()
    
    # Step 5: Generate CSV with补充 worklogs
    print("Step 5: Generating补充 worklogs CSV...")
    
    补充_worklogs = []
    
    for day in workdays_needing_hours:
        date = day['date']
        missing = day['missing_hours']
        issues = day['colleague_issues']
        
        # Sort issues: prefer VSC (Software Catalogus)
        vsc_issues = [i for i in issues if i['project'] == PREFERRED_PROJECT]
        other_issues = [i for i in issues if i['project'] != PREFERRED_PROJECT]
        prioritized_issues = vsc_issues + other_issues
        
        # Calculate proportional distribution
        total_colleague_hours = sum(i['hours'] for i in issues)
        
        for issue in prioritized_issues:
            proportion = issue['hours'] / total_colleague_hours if total_colleague_hours > 0 else 1.0 / len(issues)
            hours_to_add = missing * proportion
            
            if hours_to_add > 0.1:  # Only add if >= 6 minutes
                补充_worklogs.append({
                    'date': date,
                    'issue_key': issue['key'],
                    'issue_id': issue['id'],
                    'hours': round(hours_to_add, 2),
                    'description': f"Development work - {issue['description'][:50]}" if issue['description'] else f"Development work on {issue['key']}",
                    'original_hours': day['your_hours'],
                    'colleague_project': issue['project']
                })
    
    # Save to CSV
    output_file = '补充_worklogs_2025.csv'
    with open(output_file, 'w', newline='', encoding='utf-8') as f:
        writer = csv.DictWriter(f, fieldnames=[
            'date', 'issue_key', 'issue_id', 'hours', 'description', 
            'original_hours', 'colleague_project'
        ])
        writer.writeheader()
        writer.writerows(补充_worklogs)
    
    print(f"  ✓ Created: {output_file}")
    print()
    
    # Step 6: Summary
    print("=" * 70)
    print("SUMMARY")
    print("=" * 70)
    print()
    print(f"Days needing hours: {len(workdays_needing_hours)}")
    print(f"补充 worklogs to create: {len(补充_worklogs)}")
    print(f"Total hours to add: {sum(w['hours'] for w in 补充_worklogs):.1f}")
    print()
    
    # Breakdown by project
    by_project = defaultdict(lambda: {'count': 0, 'hours': 0})
    for wl in 补充_worklogs:
        proj = wl['colleague_project']
        by_project[proj]['count'] += 1
        by_project[proj]['hours'] += wl['hours']
    
    print("Breakdown by project:")
    for proj, data in sorted(by_project.items(), key=lambda x: x[1]['hours'], reverse=True):
        print(f"  {proj:15s}: {data['count']:3d} worklogs, {data['hours']:6.1f} hours")
    print()
    
    print("=" * 70)
    print("NEXT STEPS")
    print("=" * 70)
    print()
    print("1. Review the generated CSV:")
    print(f"   cat {output_file}")
    print()
    print("2. To import these补充 worklogs, run:")
    print(f"   python3 import_补充_worklogs.py {output_file}")
    print()

if __name__ == '__main__':
    main()

