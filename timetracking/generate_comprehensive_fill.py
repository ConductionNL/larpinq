#!/usr/bin/env python3
"""
Generate ONE comprehensive CSV that fills ALL workdays to 9 hours.

This script:
1. Analyzes all workdays in 2025
2. Checks how many hours you logged per day
3. Generates worklogs to fill each day to exactly 9 hours
4. Uses Remko's work to determine which issues to log to
"""

import requests
import os
import sys
import csv
from datetime import datetime, timedelta
from collections import defaultdict

TARGET_HOURS = 9.0
PREFERRED_PROJECT = "VSC"  # Software Catalogus

def get_account_id(jira_url, target_email, auth_email, jira_token):
    """Get Jira account ID."""
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
    """Fetch all worklogs."""
    url = f"https://api.tempo.io/4/worklogs/user/{account_id}"
    headers = {'Authorization': f'Bearer {tempo_token}'}
    params = {'from': f'{year}-01-01', 'to': f'{year}-12-31', 'limit': 1000}
    
    response = requests.get(url, headers=headers, params=params)
    if response.status_code == 200:
        return response.json().get('results', [])
    return []

def get_issue_key_from_id(jira_url, issue_id, auth_email, jira_token, cache={}):
    """Get issue key from ID with caching."""
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

def main():
    jira_token = os.environ.get('JIRA_API_TOKEN')
    tempo_token = os.environ.get('TEMPO_API_TOKEN')
    
    if not jira_token or not tempo_token:
        print("Error: Set JIRA_API_TOKEN and TEMPO_API_TOKEN")
        sys.exit(1)
    
    jira_url = "https://conduction.atlassian.net"
    your_email = "ruben@conduction.nl"
    colleague_email = "remko@conduction.nl"
    
    print("=" * 70)
    print("COMPREHENSIVE WORKDAY FILL TO 9 HOURS")
    print("=" * 70)
    print()
    
    # Step 1: Get account IDs
    print("Step 1: Getting account IDs...")
    your_id = get_account_id(jira_url, your_email, your_email, jira_token)
    colleague_id = get_account_id(jira_url, colleague_email, your_email, jira_token)
    print(f"  ✓ Your account: {your_id}")
    print(f"  ✓ Remko's account: {colleague_id}")
    print()
    
    # Step 2: Fetch worklogs
    print("Step 2: Fetching all worklogs...")
    your_worklogs = get_all_worklogs_for_user(tempo_token, your_id)
    colleague_worklogs = get_all_worklogs_for_user(tempo_token, colleague_id)
    print(f"  ✓ Your worklogs: {len(your_worklogs)}")
    print(f"  ✓ Remko's worklogs: {len(colleague_worklogs)}")
    print()
    
    # Step 3: Resolve issue keys
    print("Step 3: Resolving issue keys...")
    issue_cache = {}
    for i, wl in enumerate(colleague_worklogs):
        if i % 50 == 0:
            print(f"  Progress: {i}/{len(colleague_worklogs)}", end='\r')
        if 'issue' in wl and 'id' in wl['issue']:
            get_issue_key_from_id(jira_url, wl['issue']['id'], your_email, jira_token, issue_cache)
    print(f"  ✓ Resolved {len(issue_cache)} unique issues          ")
    print()
    
    # Step 4: Build daily data structures
    print("Step 4: Analyzing hours per day...")
    your_daily = defaultdict(float)
    colleague_daily = defaultdict(list)
    
    for wl in your_worklogs:
        date = wl['startDate']
        hours = wl['timeSpentSeconds'] / 3600
        your_daily[date] += hours
    
    for wl in colleague_worklogs:
        date = wl['startDate']
        hours = wl['timeSpentSeconds'] / 3600
        
        if 'issue' in wl and 'id' in wl['issue']:
            issue_id = wl['issue']['id']
            issue_key = issue_cache.get(issue_id, 'UNKNOWN')
        else:
            issue_id = None
            issue_key = 'UNKNOWN'
        
        colleague_daily[date].append({
            'key': issue_key,
            'id': issue_id,
            'hours': hours,
            'description': wl.get('description', ''),
            'project': issue_key.split('-')[0] if '-' in issue_key else 'UNKNOWN'
        })
    print("  ✓ Daily data organized")
    print()
    
    # Step 5: Find all workdays needing hours
    print("Step 5: Analyzing all workdays...")
    start_date = datetime(2025, 1, 1)
    end_date = datetime(2025, 12, 31)
    current = start_date
    
    workdays_to_fill = []
    skipped_no_colleague = 0
    
    while current <= end_date:
        if current.weekday() < 5:  # Monday-Friday
            date_str = current.strftime('%Y-%m-%d')
            your_hours = your_daily.get(date_str, 0)
            
            if your_hours < TARGET_HOURS:
                missing = TARGET_HOURS - your_hours
                
                if date_str in colleague_daily and colleague_daily[date_str]:
                    workdays_to_fill.append({
                        'date': date_str,
                        'current_hours': your_hours,
                        'missing_hours': missing,
                        'colleague_issues': colleague_daily[date_str]
                    })
                else:
                    skipped_no_colleague += 1
        
        current += timedelta(days=1)
    
    print(f"  Found {len(workdays_to_fill)} days to fill")
    print(f"  Skipped {skipped_no_colleague} days (Remko didn't work)")
    print()
    
    # Step 6: Generate comprehensive CSV
    print("Step 6: Generating comprehensive CSV...")
    
    all_worklogs = []
    
    for day in workdays_to_fill:
        date = day['date']
        missing = day['missing_hours']
        issues = day['colleague_issues']
        
        # Prioritize VSC issues
        vsc_issues = [i for i in issues if i['project'] == PREFERRED_PROJECT]
        other_issues = [i for i in issues if i['project'] != PREFERRED_PROJECT]
        prioritized = vsc_issues + other_issues
        
        # Distribute missing hours proportionally
        total_colleague_hours = sum(i['hours'] for i in issues)
        
        for issue in prioritized:
            if issue['id']:
                proportion = issue['hours'] / total_colleague_hours if total_colleague_hours > 0 else 1.0/len(issues)
                hours_to_add = missing * proportion
                
                if hours_to_add >= 0.05:  # Minimum 3 minutes
                    all_worklogs.append({
                        'date': date,
                        'issue_key': issue['key'],
                        'issue_id': issue['id'],
                        'hours': round(hours_to_add, 2),
                        'description': f"Development work - {issue['description'][:50]}" if issue['description'] else f"Development work on {issue['key']}",
                        'current_hours': day['current_hours'],
                        'target_hours': TARGET_HOURS,
                        'project': issue['project']
                    })
    
    # Save to CSV
    output_file = 'fill_all_workdays_to_9h.csv'
    with open(output_file, 'w', newline='', encoding='utf-8') as f:
        writer = csv.DictWriter(f, fieldnames=[
            'date', 'issue_key', 'issue_id', 'hours', 'description',
            'current_hours', 'target_hours', 'project'
        ])
        writer.writeheader()
        writer.writerows(all_worklogs)
    
    print(f"  ✓ Created: {output_file}")
    print()
    
    # Step 7: Summary
    print("=" * 70)
    print("SUMMARY")
    print("=" * 70)
    print()
    print(f"Total workdays in 2025: 261")
    print(f"Workdays to fill: {len(workdays_to_fill)}")
    print(f"Workdays skipped (no colleague work): {skipped_no_colleague}")
    print(f"Worklogs to create: {len(all_worklogs)}")
    print(f"Total hours to add: {sum(w['hours'] for w in all_worklogs):.1f}")
    print()
    
    # Breakdown by project
    by_project = defaultdict(lambda: {'count': 0, 'hours': 0})
    for wl in all_worklogs:
        proj = wl['project']
        by_project[proj]['count'] += 1
        by_project[proj]['hours'] += wl['hours']
    
    print("Hours by project:")
    for proj, data in sorted(by_project.items(), key=lambda x: x[1]['hours'], reverse=True):
        print(f"  {proj:15s}: {data['count']:3d} worklogs, {data['hours']:6.1f} hours")
    print()
    
    # Final calculation
    current_total = sum(your_daily.values())
    to_add = sum(w['hours'] for w in all_worklogs)
    final_total = current_total + to_add
    
    days_covered = len(workdays_to_fill) + skipped_no_colleague + (261 - len(workdays_to_fill) - skipped_no_colleague)
    days_at_9h = len(workdays_to_fill)
    
    print("=" * 70)
    print("FINAL TOTALS")
    print("=" * 70)
    print()
    print(f"Current hours logged: {current_total:.1f}")
    print(f"Hours to add: {to_add:.1f}")
    print(f"Final total: {final_total:.1f} hours")
    print()
    print(f"Days at 9h after import: {days_at_9h}")
    print(f"Expected total (261 workdays × 9h): 2349 hours")
    print(f"Coverage: {final_total/2349*100:.1f}%")
    print()
    print("=" * 70)
    print("NEXT STEP")
    print("=" * 70)
    print()
    print("To import these worklogs:")
    print(f"  python3 import_additional_worklogs.py {output_file}")
    print()

if __name__ == '__main__':
    main()







